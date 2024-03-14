<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OfficialReceipt;
use App\Models\Discount;
use App\Models\PaperSize;
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
                $this->printOfficialReceipt($orId, $paperSizeId, $hasTemplate);
                break;

            case 'cash-receipts-record':
                echo json_encode([
                    'data' => [
                        'filename' => $printType,
                        'pdf' => $printType,
                        'success' => 1
                    ]
                ], 201);
                break;

            case 'report-collection':
                 echo json_encode([
                    'data' => [
                        'filename' => $printType,
                        'pdf' => $printType,
                        'success' => 1
                    ]
                ], 201);
                break;

            case 'summary-fees':
                 echo json_encode([
                    'data' => [
                        'filename' => $printType,
                        'pdf' => $printType,
                        'success' => 1
                    ]
                ], 201);
                break;

            case 'e-receipts':
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

    public function printOfficialReceipt($orId, $paperSizeId, $hasTemplate = false)
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

        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        echo json_encode([
            'data' => [
                'filename' => $fileame,
                'pdf' => $pdfBase64,
                'success' => 1
            ]
        ], 201);
    }
}
