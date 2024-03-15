<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Designation;
use Illuminate\Http\Request;
use App\Models\OfficialReceipt;
use App\Models\Discount;
use App\Models\PaperSize;
use App\Models\Position;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use TCPDF;

class PrintController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $printType)
    {
        switch ($printType) {
            case 'official-receipt':
                $orId = $request->or_id;
                $paperSizeId = $request->paper_size_id;
                $hasTemplate = (int) $request->has_template ?? false;
                return $this->printOfficialReceipt($orId, $paperSizeId, $hasTemplate);

            case 'cash-receipts-record':
                $from = $request->from;
                $to = $request->to;
                $particularsIds = json_decode($request->particulars_ids);
                $certifiedCorrectId = $request->certified_correct_id;
                $paperSizeId = $request->paper_size_id;
                return $this->printCashReceiptsRecord(
                    $from,
                    $to,
                    $particularsIds,
                    $certifiedCorrectId,
                    $paperSizeId
                );

            case 'report-collection':
                $from = $request->from;
                $to = $request->to;
                $categoryIds = json_decode($request->category_ids);
                $certifiedCorrectId = $request->certified_correct_id;
                $notedById = $request->noted_by_id;
                $paperSizeId = $request->paper_size_id;
                 echo json_encode([
                    'data' => [
                        'filename' => $printType,
                        'pdf' => $printType,
                        'success' => 1
                    ]
                ], 201);
                break;

            case 'summary-fees':
                $from = $request->from;
                $to = $request->to;
                $categoryIds = json_decode($request->category_ids);
                $paperSizeId = $request->paper_size_id;
                 echo json_encode([
                    'data' => [
                        'filename' => $printType,
                        'pdf' => $printType,
                        'success' => 1
                    ]
                ], 201);
                break;

            case 'e-receipts':
                $from = $request->from;
                $to = $request->to;
                $particularsIds = json_decode($request->particulars_ids);
                $paperSizeId = $request->paper_size_id;
                 echo json_encode([
                    'data' => [
                        'filename' => $printType,
                        'pdf' => $printType,
                        'success' => 1
                    ]
                ], 201);
                break;

            default:
                return response()->json([
                    'data' => [
                        'message' => 'Invalid print type',
                        'error' => 1
                    ]
                ], 422);
                break;
        }
    }

    private function generateDateRange($from, $to) : array {
        $dates = [];
        $startDate = strtotime($from);
        $endDate = strtotime($to);

        while ($startDate <= $endDate) {
            $dates[] = date('Y-m-d', $startDate);
            $startDate = strtotime('+1 day', $startDate);
        }

        return $dates;
    }

    public function printCashReceiptsRecord(
        $from,
        $to,
        $particularsIds = [],
        $certifiedCorrectId,
        $paperSizeId
    ) : JsonResponse
    {
        $dates = $this->generateDateRange($from, $to);
        $categories = Category::with(['particulars'])
            ->whereRelation('particulars', function($query) use($particularsIds) {
                $query->whereIn('id', $particularsIds);
            })
            ->orderBy('order_no')
            ->get();

        // Get the paper size
        $paperSize = PaperSize::find($paperSizeId);
        $dimension = [
            (double) $paperSize->height,
            (double) $paperSize->width
        ];

        // Get current user
        $firstName = auth()->user()->first_name;
        $middleName = auth()->user()->middle_name ? auth()->user()->middle_name[0].'.' : '';
        $lastName = auth()->user()->last_name;
        $position = Position::find(auth()->user()->position_id);
        $designation = Designation::find(auth()->user()->designation_id);
        $station = Station::find(auth()->user()->station_id);
        $fullName = $middleName ? "$firstName $middleName $lastName" : "$firstName $lastName";

        $docTitle = "Cash Receipt Record ($from to $to)";
        $fileame = "cash_receipt_record_$from-$to.pdf";

        // Initiate PDF and configs
        $pdf = new TCPDF('P', 'in', $dimension);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(env('APP_NAME'));
        $pdf->SetTitle($docTitle);
        $pdf->SetSubject('Official Receipt');
        $pdf->SetKeywords('OR, or, Official, Receipt, official, receipt');
        $pdf->SetMargins(0.4, 0.2, 0.4);
        // $pdf->SetHeaderMargin(0);
        // $pdf->SetFooterMargin(0);
        $pdf->setPrintHeader(false);
        // $pdf->setPrintFooter(false);

        foreach ($categories as $category) {
            foreach ($category->particulars ?? [] as $particular) {
                $orCount = OfficialReceipt::with([
                        'payor', 'natureCollection', 'discount'
                    ])
                    ->where('nature_collection_id', $particular->id)
                    ->whereBetween('receipt_date', [$from, $to])
                    ->count();

                if ($orCount > 0) {
                    // Main content
                    $pdf->AddPage();

                    $paperWidth = $pdf->getPageWidth() - 0.8;

                    $pdf->SetFont('helvetica', 'B', 16);
                    $pdf->Cell(0, 0.7, 'CASH RECEIPT RECORD', 0, 1, 'C');

                    $pdf->SetFont('helvetica', '', 14);
                    $pdf->Cell(0, 0, 'REGIONAL FINANCE SERVICE OFFICE 15', 0, 1, 'C');
                    $pdf->Ln();

                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->Cell(0, 0, 'Page '.$pdf->PageNo(), 0, 1, 'R');
                    $pdf->Ln(0.05);

                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->Cell($paperWidth * 0.5062, 0, "$position->position_name $fullName", 1, 0, 'C');
                    $pdf->Cell($paperWidth * 0.2362, 0, strtoupper($designation->designation_name), 1, 0, 'C');
                    $pdf->Cell(0, 0, strtoupper($station->station_name), 1, 1, 'C');

                    $pdf->SetFont('helvetica', 'I', 10);
                    $pdf->Cell($paperWidth * 0.5062, 0, 'Accountable Personnel', 1, 0, 'C');
                    $pdf->Cell($paperWidth * 0.2362, 0, 'Official Designation', 1, 0, 'C');
                    $pdf->Cell(0, 0, 'Station', 1, 1, 'C');

                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetFillColor(197, 225, 178);
                    $pdf->MultiCell(
                        $paperWidth * 0.108, 0.45, 'Date', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        $paperWidth * 0.1003, 0.45, 'OR No.', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        $paperWidth * 0.298, 0.45, 'Name of Payor', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        $paperWidth * 0.148, 0.45, 'Nature of Collection', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        $paperWidth * 0.088, 0.45, 'Collection', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        $paperWidth * 0.131, 0.45, 'Deposit', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        0, 0.45, 'Undeposited Collection', 1, 'C', 1, 1,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );

                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetFillColor(0, 0, 0);

                    foreach ($dates as $date) {
                        $officialReceipts = OfficialReceipt::with([
                                'payor', 'natureCollection', 'discount'
                            ])
                            ->where('nature_collection_id', $particular->id)
                            ->where('receipt_date', $date)
                            ->orderBy('receipt_date')
                            ->get();
                        $totalDeposit = 0;
                        $totalUndeposit = 0;
                        $hasOrs = false;

                        foreach ($officialReceipts ?? [] as $or) {
                            $hasOrs = true;
                            $orNo = $or->or_no;
                            $payorName = strtoupper($or->payor->payor_name);
                            $natureCollection = $or->natureCollection->particular_name;
                            $collection = explode('.', number_format(($or->amount ?? 0), 2));
                            $collectionInt = $collection[0];
                            $collectionDec = $collection[1];
                            $deposit = number_format($or->deposit ?? 0, 2);
                            $undeposit = number_format(($or->amount ?? 0) - ($or->deposit ?? 0), 2);

                            $pdf->SetFont('helvetica', '', 10);

                            $pdf->MultiCell(
                                $paperWidth * 0.108, 0, $date, 1, 'C', 0, 0,
                                maxh: 0.4, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.1003, 0, $orNo, 1, 'C', 0, 0,
                                maxh: 0.4, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.298, 0, $payorName, 1, 'L', 0, 0,
                                maxh: 0.4, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.148, 0, $natureCollection, 1, 'C', 0, 0,
                                maxh: 0.4, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.044, 0, $collectionInt, 1, 'C', 0, 0,
                                maxh: 0.4, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.044, 0, $collectionDec, 1, 'C', 0, 0,
                                maxh: 0.4, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.131, 0, $deposit, 1, 'C', 0, 0,
                                maxh: 0.4, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                0, 0, $undeposit, 1, 'C', 0, 1,
                                maxh: 0.4, valign: 'M', fitcell: true
                            );

                            $totalDeposit += $or->deposit ?? 0;
                            //$totalUndeposit += $undeposit;
                        }

                        if ($hasOrs) {
                            $pdf->SetFont('helvetica', 'B', 10);

                            $pdf->MultiCell(
                                $paperWidth * 0.108, 0.2, $date, 1, 'C', 0, 0,
                                maxh: 0.2, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.1003, 0.2, 'DEPOSIT', 1, 'C', 0, 0,
                                maxh: 0.2, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.298, 0.2, '', 1, 'C', 0, 0,
                                maxh: 0.2, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.148, 0.2, '', 1, 'C', 0, 0,
                                maxh: 0.2, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.044, 0.2, '', 1, 'C', 0, 0,
                                maxh: 0.2, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.044, 0.2, '', 1, 'C', 0, 0,
                                maxh: 0.2, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                $paperWidth * 0.131, 0.2, number_format($totalDeposit, 2), 1, 'C', 0, 0,
                                maxh: 0.2, valign: 'M', fitcell: true
                            );
                            $pdf->MultiCell(
                                0, 0.2, number_format($totalUndeposit, 2), 1, 'C', 0, 1,
                                maxh: 0.2, valign: 'M', fitcell: true
                            );
                        }
                    }
                }
            }
        }

        $pdfBlob = $pdf->Output($fileame, 'S');
        $pdfBase64 = base64_encode($pdfBlob);

        return response()->json([
            'data' => [
                'filename' => $fileame,
                'pdf' => $pdfBase64,
                'success' => 1
            ]
        ], 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/json');
    }

    public function printOfficialReceipt($orId, $paperSizeId, $hasTemplate = false) : JsonResponse
    {
        $with = [
            'accountablePersonnel', 'payor', 'natureCollection', 'discount'
        ];
        // Get the official receipt
        $officialReceipt = OfficialReceipt::with($with)->find($orId);

        if (!$officialReceipt) {
            $officialReceipt = OfficialReceipt::with($with)
                ->where('or_no', $orId)
                ->first();
        }

        $discount = Discount::find($officialReceipt->discount_id);

        $orDate = date('m/d/Y', strtotime($officialReceipt->receipt_date));
        $orNo = $officialReceipt->or_no;
        $payorName = strtoupper($officialReceipt->payor->payor_name);
        $discountName = $discount ? "\n\n\n$discount->discount_name\n$officialReceipt->card_no" :
            '';
        $natureCollection = strtoupper(
            $officialReceipt->natureCollection->particular_name .
            $discountName
        );
        $amount = number_format($officialReceipt->amount, 2);
        $amountInWords = strtoupper($officialReceipt->amount_words);
        $personnelName = strtoupper(
            $officialReceipt->accountablePersonnel->first_name . ' ' .
            $officialReceipt->accountablePersonnel->last_name
        );
        $paymentMode = strtolower($officialReceipt->payment_mode);
        $draweeBank = strtoupper($officialReceipt->drawee_bank);
        $checkNo = strtoupper($officialReceipt->check_no);
        $checkDate = $officialReceipt->check_date ?
            date('m/d/Y', strtotime($officialReceipt->check_date)) : '';

        // Get the paper size
        $paperSize = PaperSize::find($paperSizeId);
        $dimension = [
            (double) $paperSize->height,
            (double) $paperSize->width
        ];

        $docTitle = "Official Receipt ($officialReceipt->or_no)";
        $fileame = "or-$officialReceipt->or_no.pdf";

        // Initiate PDF and configs
        $pdf = new TCPDF('P', 'in', $dimension);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(env('APP_NAME'));
        $pdf->SetTitle($docTitle);
        $pdf->SetSubject('Official Receipt');
        $pdf->SetKeywords('OR, or, Official, Receipt, official, receipt');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->SetAutoPageBreak(FALSE, 0);
        $pdf->setImageScale(100);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Main content
        $pdf->AddPage();

        if ($hasTemplate) {
            $pdf->Image('images/or-template-2.jpg', 0, 0, $dimension[1], $dimension[0], 'JPEG');
        }

        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('helvetica', 'B', 13);

        // Generate a cell
        $pdf->SetXY(0.25, 1.77);
        $pdf->Cell(1.6, 0, $orDate, 0, 0, 'R');
        $pdf->Cell(0.5, 0, '', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 18.5);
        $pdf->SetTextColor(188,113,136);
        $pdf->SetXY(2.45, 1.65);
        $pdf->Cell(1.4, 0, $hasTemplate ? $orNo : '', 0, 1, 'L');

        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('helvetica', 'B', 12);

        $pdf->SetXY(0.28, 2.32);
        $pdf->Cell(2.6, 0, $payorName, 0, 0, 'L');
        $pdf->Cell(2, 0, '', 0, 1, 'R');

        if (strlen($natureCollection) > 150) {
            $pdf->setCellHeightRatio(1.63);
            $pdf->SetFont('helvetica', 'B', 9);
        } else {
            $pdf->setCellHeightRatio(1.32);
            $pdf->SetFont('helvetica', 'B', 11);
        }

        $pdf->SetXY(0.25, 2.96);
        $pdf->MultiCell(1.6, 2.2, $natureCollection, 0, 'L', 0, 0);
        $pdf->setCellHeightRatio(1.25);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->MultiCell(2, 2.2, $amount, 0, 'R', 0, 1);

        $pdf->SetXY(0.25, 5.18);
        $pdf->Cell(1.6, 0, '', 0, 0, 'L');
        $pdf->Cell(2, 0, $amount, 0, 1, 'R');

        $pdf->SetFont('helvetica', 'B', strlen($amountInWords) >= 35 ? 8 : 11);

        $pdf->SetXY(0.25, strlen($amountInWords) >= 35 ? 5.6 : 5.64);
        $pdf->MultiCell(3.6, 0, $amountInWords, 0, 'L', 0, 1);

        $pdf->SetFont('zapfdingbats', '', 12);

        switch ($paymentMode) {
            case 'cash':
                $pdf->SetXY(0.3, 6.02);
                $pdf->Cell(1.6, 0, '4', 0, 1, 'L');
                break;
            case 'check':
                $pdf->SetXY(0.3, 6.222);
                $pdf->Cell(1.35, 0, '4', 0, 1, 'L');

                $pdf->SetXY(1.6, 6.28);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->MultiCell(0.73, 0, $draweeBank, 0, 'L', false, 0);
                $pdf->MultiCell(0.75, 0, $checkNo, 0, 'L', false, 0);
                $pdf->MultiCell(0.84, 0, $checkDate, 0, 'L');
                break;
            case 'money_order':
                $pdf->SetXY(0.3, 6.422);
                $pdf->Cell(1.6, 0, '4', 0, 1, 'L');
                break;
            default:
                break;
        }

        $pdf->SetFont('helvetica', 'B', 9);

        $pdf->SetXY(0.25, 7.19);
        $pdf->Cell(1.85, 0, '', 0, 0, 'L');
        $pdf->Cell(1.75, 0, $personnelName, 0, 1, 'C');

        //$pdfBlob = $pdf->Output($fileame, 'I');

        $pdfBlob = $pdf->Output($fileame, 'S');
        $pdfBase64 = base64_encode($pdfBlob);

        return response()->json([
            'data' => [
                'filename' => $fileame,
                'pdf' => $pdfBase64,
                'success' => 1
            ]
        ], 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/json');
    }
}
