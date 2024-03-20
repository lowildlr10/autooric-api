<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Designation;
use Illuminate\Http\Request;
use App\Models\OfficialReceipt;
use App\Models\Discount;
use App\Models\PaperSize;
use App\Models\Position;
use App\Models\Signatory;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use PhpParser\Node\Expr\Cast\Object_;
use TCPDF;
use TCPDF_FONTS;

class PrintController extends Controller
{
    public function __construct()
    {
        $this->fontArial = TCPDF_FONTS::addTTFfont(public_path('fonts/arial.ttf'), 'TrueTypeUnicode', '', 96);
        $this->fontArialBold = TCPDF_FONTS::addTTFfont(public_path('fonts/arialbd.ttf'), 'TrueTypeUnicode', '', 96);
        $this->fontArialItalic = TCPDF_FONTS::addTTFfont(public_path('fonts/ariali.ttf'), 'TrueTypeUnicode', '', 96);
        $this->fontArialBoldItalic = TCPDF_FONTS::addTTFfont(public_path('fonts/arialbi.ttf'), 'TrueTypeUnicode', '', 96);
        $this->fontArialNarrow = TCPDF_FONTS::addTTFfont(public_path('fonts/arialn.ttf'), 'TrueTypeUnicode', '', 96);
        $this->fontArialNarrowBold = TCPDF_FONTS::addTTFfont(public_path('fonts/arialnb.ttf'), 'TrueTypeUnicode', '', 96);
    }

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
                return $this->printEReceipts(
                    $from,
                    $to,
                    $particularsIds,
                    $paperSizeId
                );

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

    private function getSignatory($signatoryId, $signatoryType) : Object
    {
        // Get Certified Correct Signatory
        $signatory = Signatory::find($signatoryId);
        $signatory->report_module = json_decode($signatory->report_module);
        $position = '';
        $designation = '';
        $station = '';

        foreach ($signatory->report_module ?? [] as $module) {
            if ($module->is_enabled && $module->report === $signatoryType) {
                $positionObj = Position::find($module->position_id);
                $designationObj = Designation::find($module->designation_id);
                $stationObj = Station::find($module->station_id);

                $position = $positionObj->position_name;
                $designation = $designationObj->designation_name;
                $station = $stationObj->station_name;
                break;
            }
        }

        return (Object) [
            'signatory_name' => $signatory->signatory_name,
            'position' => $position,
            'designation' => $designation,
            'station' => $station
        ];
    }

    private function getPaperDimensions($paperSizeId) : array
    {
        $paperSize = PaperSize::find($paperSizeId);
        return [
            (double) $paperSize->height,
            (double) $paperSize->width
        ];
    }

    private function printEReceipts(
        $from,
        $to,
        $particularsIds = [],
        $paperSizeId
    ) : JsonResponse
    {
        $dates = $this->generateDateRange($from, $to);
        $categories = Category::with(['particulars' => function($query) use($particularsIds) {
                $query->whereIn('id', $particularsIds);
            }])
            ->orderBy('order_no')
            ->get();

        // Get the paper size
        $dimension = $this->getPaperDimensions($paperSizeId);

        // Get the OR paper size
        $orPaperSize = PaperSize::where('paper_name', 'LIKE', '%receipt%')->first();
        $orDimension = $this->getPaperDimensions($orPaperSize->id);

        $docTitle = "E-Receipts ($from to $to)";
        $filename = "e_receipts_$from".'_'."$to.pdf";

        // Initiate PDF and configs
        $pdf = new TCPDF('P', 'in', $dimension);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(env('APP_NAME'));
        $pdf->SetTitle($docTitle);
        $pdf->SetSubject('E-Receipts');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->SetAutoPageBreak(FALSE, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        foreach ($categories as $category) {
            foreach ($category->particulars ?? [] as $particular) {
                $orCount = OfficialReceipt::with([
                        'payor', 'natureCollection', 'discount'
                    ])
                    ->where('nature_collection_id', $particular->id)
                    ->where(function($query) use($dates) {
                        $query->whereIn('deposited_date', $dates)
                            ->orWhereIn('cancelled_date', $dates);
                    })
                    ->count();

                if ($orCount > 0) {
                    // Main content
                    $pdf->AddPage();

                    $paperWidth = $pdf->getPageWidth() - 0.8;

                    foreach ($dates as $date) {
                        $officialReceipts = OfficialReceipt::with([
                                'payor', 'natureCollection', 'discount'
                            ])
                            ->where('nature_collection_id', $particular->id)
                            ->where(function($query) use($date) {
                                $query->where('deposited_date', $date)
                                    ->orWhere('cancelled_date', $date);
                            })
                            ->orderBy('created_at')
                            ->get();
                        $orCounter = 0;

                        foreach ($officialReceipts ?? [] as $orKey => $or) {
                            $discount = Discount::find($or->discount_id);

                            $orDate = date('m/d/Y', strtotime($or->receipt_date));
                            $orNo = $or->or_no;
                            $payorName = strtoupper($or->payor->payor_name);
                            $discountName = $discount ? "\n\n\n$discount->discount_name\n$or->card_no" :
                                '';
                            $natureCollection = strtoupper(
                                $or->natureCollection->particular_name .
                                $discountName
                            );
                            $amount = number_format($or->amount, 2);
                            $amountInWords = strtoupper($or->amount_words);
                            $personnelName = strtoupper(
                                $or->accountablePersonnel->first_name . ' ' .
                                $or->accountablePersonnel->last_name
                            );
                            $paymentMode = strtolower($or->payment_mode);
                            $draweeBank = strtoupper($or->drawee_bank);
                            $checkNo = strtoupper($or->check_no);
                            $checkDate = $or->check_date ?
                                date('m/d/Y', strtotime($or->check_date)) : '';
                            $isCancelled = !!$or->cancelled_date;

                            $pdf->SetY($pdf->getPageHeight() * 0.077);

                            if ($orCounter === 0) {
                                $pdf->SetX(0.1);
                            } else {
                                $pdf->SetX(($pdf->getPageWidth() / 2));
                            }

                            $currentX = $pdf->GetX();
                            $currentY = $pdf->GetY();

                            $this->generateOfficialReceiptSegment(
                                $pdf, $currentX, $currentY, $orDimension[1], $orDimension[0],
                                hasTemplate: true,
                                orDate: $orDate,
                                orNo: $orNo,
                                payorName: $payorName,
                                natureCollection: $natureCollection,
                                amount: $amount,
                                amountInWords: $amountInWords,
                                paymentMode: $paymentMode,
                                draweeBank: $draweeBank,
                                checkNo: $checkNo,
                                checkDate: $checkDate,
                                personnelName: $personnelName,
                                isCancelled: $isCancelled
                            );

                            if ($orCounter === 1) {
                                $orCounter = 0;

                                if ($orKey !== count($officialReceipts)) {
                                    $pdf->AddPage();
                                }
                            } else if ($orCounter === 0) {
                                $orCounter++;
                            }
                        }
                    }
                }
            }
        }

        $pdfBlob = $pdf->Output($filename, 'S');
        $pdfBase64 = base64_encode($pdfBlob);

        return response()->json([
            'data' => [
                'filename' => $filename,
                'pdf' => $pdfBase64,
                'success' => 1
            ]
        ], 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/json');
    }

    private function printCashReceiptsRecord(
        $from,
        $to,
        $particularsIds = [],
        $certifiedCorrectId,
        $paperSizeId
    ) : JsonResponse
    {
        $dates = $this->generateDateRange($from, $to);
        $categories = Category::with(['particulars' => function($query) use($particularsIds) {
                $query->whereIn('id', $particularsIds);
            }])
            ->orderBy('order_no')
            ->get();

        // Get the paper size
        $dimension = $this->getPaperDimensions($paperSizeId);

        // Get current user
        $firstName = auth()->user()->first_name;
        $middleName = auth()->user()->middle_name ? auth()->user()->middle_name[0].'.' : '';
        $lastName = auth()->user()->last_name;
        $position = Position::find(auth()->user()->position_id);
        $designation = Designation::find(auth()->user()->designation_id);
        $station = Station::find(auth()->user()->station_id);
        $fullName = strtoupper($middleName ? "$firstName $middleName $lastName" : "$firstName $lastName");

        // Get Certified Correct Signatory
        $certifiedCorrect = $this->getSignatory($certifiedCorrectId, 'crr_certified_correct');
        $certifiedCorrectName = strtoupper($certifiedCorrect->signatory_name);
        $certifiedCorrectPosition = $certifiedCorrect->position;
        $certifiedCorrectDesignation = $certifiedCorrect->designation;

        $docTitle = "Cash Receipt Record ($from to $to)";
        $filename = "cash_receipt_record_$from".'_'."$to.pdf";

        // Initiate PDF and configs
        $pdf = new TCPDF('P', 'in', $dimension);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(env('APP_NAME'));
        $pdf->SetTitle($docTitle);
        $pdf->SetSubject('Cash Receipts Record');
        $pdf->SetMargins(0.4, 0.3, 0.4);
        $pdf->setPrintHeader(false);

        foreach ($categories as $category) {
            foreach ($category->particulars ?? [] as $particular) {
                $orCount = OfficialReceipt::with([
                        'payor', 'natureCollection', 'discount'
                    ])
                    ->where('nature_collection_id', $particular->id)
                    ->where(function($query) use($dates) {
                        $query->whereIn('deposited_date', $dates)
                            ->orWhereIn('cancelled_date', $dates);
                    })
                    ->count();

                if ($orCount > 0) {
                    // Main content
                    $pdf->AddPage();

                    $paperWidth = $pdf->getPageWidth() - 0.8;

                    $pdf->SetFont($this->fontArialBold, 'B', 16);
                    $pdf->Cell(0, 0.7, 'CASH RECEIPT RECORD', 0, 1, 'C');

                    $pdf->SetFont($this->fontArial, '', 14);
                    $pdf->Cell(0, 0, 'REGIONAL FINANCE SERVICE OFFICE 15', 0, 1, 'C');
                    $pdf->Ln();

                    $pdf->SetFont($this->fontArial, '', 10);
                    $pdf->Cell(0, 0, 'Page 1', 0, 1, 'R');
                    $pdf->Ln(0.05);

                    $pdf->SetFont($this->fontArialBold, 'B', 10);
                    $pdf->Cell($paperWidth * 0.46, 0, "$position->position_name $fullName", 1, 0, 'C');
                    $pdf->Cell($paperWidth * 0.31, 0, strtoupper($designation->designation_name), 1, 0, 'C');
                    $pdf->Cell(0, 0, strtoupper($station->station_name), 1, 1, 'C');

                    $pdf->SetFont($this->fontArial, 'I', 10);
                    $pdf->Cell($paperWidth * 0.46, 0, 'Accountable Personnel', 1, 0, 'C');
                    $pdf->Cell($paperWidth * 0.31, 0, 'Official Designation', 1, 0, 'C');
                    $pdf->Cell(0, 0, 'Station', 1, 1, 'C');

                    $pdf->SetFont($this->fontArialBold, 'B', 10);
                    $pdf->SetFillColor(197, 225, 178);
                    $pdf->MultiCell(
                        $paperWidth * 0.12, 0.45, 'Date', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        $paperWidth * 0.09, 0.45, 'OR No.', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        $paperWidth * 0.25, 0.45, 'Name of Payor', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        $paperWidth * 0.2, 0.45, 'Nature of Collection', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        $paperWidth * 0.11, 0.45, 'Collection', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->MultiCell(
                        $paperWidth * 0.11, 0.45, 'Deposit', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );
                    $pdf->SetFont($this->fontArialBold, 'B', 9.5);
                    $pdf->MultiCell(
                        0, 0.45, 'Undeposited Collection', 1, 'C', 1, 0,
                        maxh: 0.45, valign: 'M', fitcell: true
                    );

                    $pdf->Ln();

                    $pdf->SetFont($this->fontArial, '', 10);
                    $pdf->SetFillColor(0, 0, 0);

                    foreach ($dates as $date) {
                        $officialReceipts = OfficialReceipt::with([
                                'payor', 'natureCollection', 'discount'
                            ])
                            ->where('nature_collection_id', $particular->id)
                            ->where(function($query) use($date) {
                                $query->where('deposited_date', $date)
                                    ->orWhere('cancelled_date', $date);
                            })
                            ->orderBy('created_at')
                            ->get();
                        $totalDeposit = 0;
                        $totalUndeposit = 0;
                        $hasOrs = false;

                        foreach ($officialReceipts ?? [] as $or) {
                            $hasOrs = true;
                            $orNo = $or->or_no;
                            $receiptDate = date("m/d/Y", strtotime($or->receipt_date));
                            $isCancelled = !!$or->cancelled_date;
                            $payorName = strtoupper($or->payor->payor_name);
                            $natureCollection = strtoupper($or->natureCollection->particular_name);
                            $collection = explode('.', number_format(($or->amount ?? 0), 2));
                            $collectionInt = $collection[0];
                            $collectionDec = $collection[1];
                            $deposit = number_format($or->deposit, 2);
                            $undeposit = number_format($or->amount - $or->deposit, 2);

                            $pdf->SetFont($this->fontArialNarrow, '', 11);

                            $htmlFontColor = '#000';

                            if (!$isCancelled) {
                                $totalDeposit += $or->deposit;
                                $totalUndeposit += $or->amount - $or->deposit;
                            } else {
                                $htmlFontColor = '#FF0000';
                            }

                            $htmlTable = '<table border="1" cellpadding="2"><tr>';
                            $htmlTable .= '
                                <td
                                    width="12%"
                                    align="center"
                                    style="'."color: $htmlFontColor".'"
                                >' . $receiptDate . '</td>
                                <td
                                    width="9%"
                                    align="center"
                                    style="'."color: $htmlFontColor".'"
                                >' . $orNo . '</td>
                                <td
                                    width="25%"
                                    align="left"
                                    style="'."color: $htmlFontColor".'"
                                >' . (!$isCancelled ? $payorName : 'CANCELLED') . '</td>
                                <td
                                    width="20%"
                                    align="center"
                                    style="'."color: $htmlFontColor".'"
                                >' . $natureCollection . '</td>
                                <td
                                    width="6.6%"
                                    align="center"
                                    style="'."color: $htmlFontColor".'"
                                >' . (!$isCancelled ? $collectionInt : '00') . '</td>
                                <td
                                    width="4.4%"
                                    align="center"
                                    style="'."color: $htmlFontColor".'"
                                >' . (!$isCancelled ? $collectionDec : '00') . '</td>
                                <td
                                    width="11%"
                                    align="center"
                                ></td>
                                <td
                                    width="12%"
                                    align="center"
                                ></td>
                            ';

                            $htmlTable .= '</tr></table>';

                            $pdf->writeHTML($htmlTable, ln: false);
                        }

                        if ($hasOrs) {
                            $pdf->SetFont($this->fontArialNarrowBold, 'B', 11);

                            $htmlTable = '<table border="1" cellpadding="2"><tr>';
                            $htmlTable .= '
                                <td width="12%" align="center">' . date("m/d/Y", strtotime($date)) . '</td>
                                <td width="9%" align="center">DEPOSIT</td>
                                <td width="25%" align="left"></td>
                                <td width="20%" align="center"></td>
                                <td width="6.6%" align="center"></td>
                                <td width="4.4%" align="center"></td>
                                <td width="11%" align="center">' . number_format($totalDeposit, 2) . '</td>
                                <td width="12%" align="center"></td>
                            ';
                            $htmlTable .= '</tr></table>';

                            $pdf->writeHTML($htmlTable, ln: false);
                        }
                    }

                    $pdf->Ln(0.1);

                    $dateFrom = date("F Y", strtotime($from));
                    $dateTo = date("F Y", strtotime($to));
                    $certDate = $dateFrom;

                    if ($dateFrom === $dateTo) {
                        $certDate = strtoupper($dateFrom);
                    } else {
                        $dateFromMonth = date("F", strtotime($from));
                        $dateToMonth = date("F", strtotime($to));
                        $dateFromYear = date("Y", strtotime($from));
                        $dateToYear = date("Y", strtotime($to));

                        if ($dateFromYear === $dateToYear) {
                            $certDate = strtoupper("$dateFromMonth to $dateToMonth $dateFromYear");
                        } else {
                            $certDate = strtoupper("$dateFrom to $dateTo");
                        }
                    }

                    $pdf->SetFont($this->fontArialBold, 'B', 12);
                    $pdf->Cell(0, 0.3, 'C E R T I F I C A T I O N', 'LTR', 1, 'C');
                    $pdf->Cell(0, 0.2, '', 'LR', 1, 'C');
                    $pdf->SetFont('helvetica', '', 12);
                    $pdf->MultiCell($paperWidth * 0.07, 1, '', 'L', 'L', 0, 0);
                    $pdf->MultiCell($paperWidth * 0.86, 0.8,
                        "          I hereby certify that the foregoing is a correct and complete record of all\n".
                        "collections and deposits had by me in my capacity as Collecting Officer of Regional\n".
                        "Finance Service Office 15 during the period from <strong>$certDate</strong> inclusives, as\n".
                        "indicated in the corresponding columns.",
                        0, 'L', 0, 0,
                        ishtml: true
                    );
                    $pdf->MultiCell(0, 1, '', 'R', 'L', 0, 1);
                    $pdf->SetFont($this->fontArialBold, 'B', 12);
                    $pdf->Cell(0, 0.7, "$position->position_name $fullName                 ", 'LBR', 1, 'R');
                    $pdf->Ln(0.7);

                    $pdf->SetFont($this->fontArial, '', 12);
                    $pdf->Cell($paperWidth * 0.04, 0, "", 0, 0, 'L');
                    $pdf->Cell($paperWidth * 0.3, 0, "Certified Correct by:", 0, 1, 'L');
                    $pdf->Ln(0.5);

                    $pdf->SetFont($this->fontArialBold, 'BU', 12);
                    $pdf->Cell($paperWidth * 0.04, 0, "", 0, 0, 'L');
                    $pdf->Cell($paperWidth * 0.3, 0, "$certifiedCorrectPosition $certifiedCorrectName", 0, 1, 'L');
                    $pdf->SetFont($this->fontArial, '', 12);
                    $pdf->Cell($paperWidth * 0.04, 0, "", 0, 0, 'L');
                    $pdf->Cell($paperWidth * 0.3, 0, $certifiedCorrectDesignation, 0, 1, 'L');
                }
            }
        }

        $pdfBlob = $pdf->Output($filename, 'S');
        $pdfBase64 = base64_encode($pdfBlob);

        return response()->json([
            'data' => [
                'filename' => $filename,
                'pdf' => $pdfBase64,
                'success' => 1
            ]
        ], 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/json');
    }

    private function generateOfficialReceiptSegment(
        TCPDF $pdf,
        $x = 0,
        $y = 0,
        $w = 0,
        $h = 0,
        $hasTemplate,
        $orDate,
        $orNo,
        $payorName,
        $natureCollection,
        $amount,
        $amountInWords,
        $paymentMode,
        $draweeBank,
        $checkNo,
        $checkDate,
        $personnelName,
        $isCancelled = false
    ) : void
    {
        if ($hasTemplate) {
            $pdf->Image('images/or-template-2.jpg', $x, $y, $w, $h, 'JPEG');
        }

        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('helvetica', 'B', 13);

        // Generate a cell
        $pdf->SetXY($x, $y + 1.77);
        $pdf->Cell(1.6, 0, $orDate, 0, 0, 'R');
        $pdf->Cell(0.5, 0, '', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 18.5);
        $pdf->SetTextColor(188,113,136);
        $pdf->SetXY($x + 2.45, $y + 1.65);
        $pdf->Cell(1.4, 0, $hasTemplate ? $orNo : '', 0, 1, 'L');

        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('helvetica', 'B', 12);

        $pdf->SetXY($x+ 0.28, $y + 2.32);
        $pdf->Cell(2.6, 0, $payorName, 0, 0, 'L');
        $pdf->Cell(2, 0, '', 0, 1, 'R');

        if (strlen($natureCollection) > 150) {
            $pdf->setCellHeightRatio(1.63);
            $pdf->SetFont('helvetica', 'B', 9);
        } else {
            $pdf->setCellHeightRatio(1.32);
            $pdf->SetFont('helvetica', 'B', 11);
        }

        $pdf->SetXY($x + 0.25, $y + 2.96);
        $pdf->MultiCell(1.6, 2.2, $natureCollection, 0, 'L', 0, 0);
        $pdf->setCellHeightRatio(1.25);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->MultiCell(2, 2.2, $amount, 0, 'R', 0, 1);

        $pdf->SetXY($x + 0.25, $y + 5.18);
        $pdf->Cell(1.6, 0, '', 0, 0, 'L');
        $pdf->Cell(2, 0, $amount, 0, 1, 'R');

        $pdf->SetFont('helvetica', 'B', strlen($amountInWords) >= 35 ? 8 : 11);

        $pdf->SetXY($x + 0.25, $y + (strlen($amountInWords) >= 35 ? 5.6 : 5.64));
        $pdf->MultiCell(3.6, 0, $amountInWords, 0, 'L', 0, 1);

        $pdf->SetFont('zapfdingbats', '', 12);

        switch ($paymentMode) {
            case 'cash':
                $pdf->SetXY($x + 0.3, $y + 6.02);
                $pdf->Cell(1.6, 0, '4', 0, 1, 'L');
                break;
            case 'check':
                $pdf->SetXY($x + 0.3, $y + 6.222);
                $pdf->Cell(1.35, 0, '4', 0, 1, 'L');

                $pdf->SetXY($x + 1.6, $y + 6.28);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->MultiCell(0.73, 0, $draweeBank, 0, 'L', false, 0);
                $pdf->MultiCell(0.75, 0, $checkNo, 0, 'L', false, 0);
                $pdf->MultiCell(0.84, 0, $checkDate, 0, 'L');
                break;
            case 'money_order':
                $pdf->SetXY($x + 0.3, $y + 6.422);
                $pdf->Cell(1.6, 0, '4', 0, 1, 'L');
                break;
            default:
                break;
        }

        $pdf->SetFont('helvetica', 'B', 9);

        $pdf->SetXY($x + 0.25, $y + 7.19);
        $pdf->Cell(1.85, 0, '', 0, 0, 'L');
        $pdf->Cell(1.75, 0, $personnelName, 0, 1, 'C');

        if ($isCancelled) {
            $pdf->SetXY($x + ($w * 0.123), ($y + $h * 0.47));
            $pdf->SetTextColor(255,109,109);
            $pdf->SetFont('helvetica', 'B', ($w * 8.66));
            $pdf->Cell($w, 0, 'CANCELLED');
        }
    }

    private function printOfficialReceipt($orId, $paperSizeId, $hasTemplate = false) : JsonResponse
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
        $dimension = $this->getPaperDimensions($paperSizeId);

        $docTitle = "Official Receipt ($officialReceipt->or_no)";
        $filename = "or-$officialReceipt->or_no.pdf";

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

        $this->generateOfficialReceiptSegment(
            $pdf, 0, 0, $dimension[1], $dimension[0],
            hasTemplate: $hasTemplate,
            orDate: $orDate,
            orNo: $orNo,
            payorName: $payorName,
            natureCollection: $natureCollection,
            amount: $amount,
            amountInWords: $amountInWords,
            paymentMode: $paymentMode,
            draweeBank: $draweeBank,
            checkNo: $checkNo,
            checkDate: $checkDate,
            personnelName: $personnelName
        );

        //$pdfBlob = $pdf->Output($filename, 'I');

        $pdfBlob = $pdf->Output($filename, 'S');
        $pdfBase64 = base64_encode($pdfBlob);

        return response()->json([
            'data' => [
                'filename' => $filename,
                'pdf' => $pdfBase64,
                'success' => 1
            ]
        ], 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/json');
    }
}
