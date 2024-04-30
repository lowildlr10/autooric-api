<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Account;
use App\Models\Designation;
use Illuminate\Http\Request;
use App\Models\OfficialReceipt;
use App\Models\Particular;
use App\Models\Discount;
use App\Models\PaperSize;
use App\Models\Position;
use App\Models\Signatory;
use App\Models\Station;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use PhpParser\Node\Expr\Cast\Object_;
use TCPDF;
use TCPDF_FONTS;

class PrintController extends Controller
{
    public function __construct()
    {
        $this->appUrl = env('APP_URL') ?? 'http://localhost';
        $this->fontArial = TCPDF_FONTS::addTTFfont('fonts/arial.ttf', 'TrueTypeUnicode', '', 96);
        $this->fontArialBold = TCPDF_FONTS::addTTFfont('fonts/arialbd.ttf', 'TrueTypeUnicode', '', 96);
        $this->fontArialItalic = TCPDF_FONTS::addTTFfont('fonts/ariali.ttf', 'TrueTypeUnicode', '', 96);
        $this->fontArialBoldItalic = TCPDF_FONTS::addTTFfont('fonts/arialbi.ttf', 'TrueTypeUnicode', '', 96);
        $this->fontArialNarrow = TCPDF_FONTS::addTTFfont('fonts/arialn.ttf', 'TrueTypeUnicode', '', 96);
        $this->fontArialNarrowBold = TCPDF_FONTS::addTTFfont('fonts/arialnb.ttf', 'TrueTypeUnicode', '', 96);
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

            case 'preview-report-collection':
                $from = $request->from;
                $to = $request->to;
                $categoryIds = json_decode($request->category_ids);
                $certifiedCorrectId = $request->certified_correct_id;
                $notedById = $request->noted_by_id;
                $paperSizeId = $request->paper_size_id;
                return $this->printReportCollectionData(
                    $from,
                    $to,
                    $categoryIds,
                    $certifiedCorrectId,
                    $notedById,
                    $paperSizeId,
                    true,
                    'coa_accounting'
                );

            case 'report-collection':
                $template = $request->template;

                $from = $request->from;
                $to = $request->to;
                $categoryIds = json_decode($request->category_ids);
                $certifiedCorrectId = $request->certified_correct_id;
                $notedById = $request->noted_by_id;
                $paperSizeId = $request->paper_size_id;

                if ($template === 'coa_accounting') {
                    $printData = $request->print_data;
                    return $this->printReportCollectionCoaAccounting(
                        $printData
                    );
                } else if ($template === 'pnp_crame') {
                    $printData = $this->printReportCollectionData(
                        $from,
                        $to,
                        $categoryIds,
                        $certifiedCorrectId,
                        $notedById,
                        $paperSizeId,
                        false,
                        $template
                    );
                    return $this->printReportCollectionCrame(
                        $printData
                    );
                } else if ($template === 'firearms_registration') {
                    $printData = $this->printReportCollectionData(
                        $from,
                        $to,
                        $categoryIds,
                        $certifiedCorrectId,
                        $notedById,
                        $paperSizeId,
                        false,
                        $template
                    );
                    return $this->printReportCollectionFirearmsReg(
                        $printData
                    );
                }
                break;

            case 'summary-fees':
                // Fetch the PDF file content
                $pdfContent = file_get_contents(url('/docs/complete_kinds_fees.pdf'));

                if ($pdfContent === false) {
                    return response()->json([
                        'data' => [
                            'message' => 'Failed to fetch PDF file.',
                            'error' => 1
                        ]
                    ], 422)
                        ->header('Access-Control-Allow-Origin', '*')
                        ->header('Content-Type', 'application/json');
                    exit;
                }

                // Convert the PDF content to Base64
                $pdfBase64 = base64_encode($pdfContent);

                return response()->json([
                    'data' => [
                        'filename' => 'Kinds of Fees',
                        'pdf' => $pdfBase64,
                        'success' => 1
                    ]
                ], 201)
                    ->header('Access-Control-Allow-Origin', '*')
                    ->header('Content-Type', 'application/json');

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

    private function getUserESignature($id)
    {
        $user = User::find($id);

        if (!empty($user->esig)) {
            $publicPath = str_replace("$this->appUrl/", '', $user->esig);
            return $publicPath;
        }

        return null;
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

                $position = $positionObj ? $positionObj->position_name : '';
                $designation = $designationObj ? $designationObj->designation_name : '';
                $station = $stationObj ? $stationObj->station_name : '';
                break;
            }
        }

        return (Object) [
            'signatory_name' => ucwords(strtolower($signatory->signatory_name)),
            'position' => ucwords(strtolower($position)),
            'designation' => ucwords(strtolower($designation)),
            'station' => ucwords(strtolower($station))
        ];
    }

    private function getCurrentUser() : Object
    {
        $firstName = ucwords(strtolower(auth()->user()->first_name));
        $middleName = ucwords(
            strtolower(auth()->user()->middle_name ? auth()->user()->middle_name[0].'.' : ''
        ));
        $lastName = ucwords(strtolower(auth()->user()->last_name));
        $position = Position::find(auth()->user()->position_id);
        $positionName = ucwords(strtolower($position->position_name));
        $designation = Designation::find(auth()->user()->designation_id);
        $desinationName = $designation->designation_name;
        $station = Station::find(auth()->user()->station_id);
        $stationName = ucwords(strtolower($station->station_name));
        $fullName = $middleName ? "$firstName $middleName $lastName" : "$firstName $lastName";

        return (Object) [
            'name' => $fullName,
            'position' => $positionName,
            'designation' => $desinationName,
            'station' => $stationName
        ];
    }

    private function getCertDateRange($from, $to) : string
    {
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
                $certDate = strtoupper("$dateFromMonth - $dateToMonth $dateFromYear");
            } else {
                $certDate = strtoupper("$dateFrom - $dateTo");
            }
        }

        return $certDate;
    }

    private function getPaperDimensions($paperSizeId) : array
    {
        $paperSize = PaperSize::find($paperSizeId);
        return [
            (double) $paperSize->height,
            (double) $paperSize->width
        ];
    }

    private function generateHeaderSection(TCPDF $pdf, $hasEmail = false) : void
    {
        $paperWidth = $pdf->getPageWidth();
        $paperHeight = $pdf->getPageHeight();

        $pdf->Image('images/pnp-logo.png', $paperWidth * 0.193, 0.72, 0.55, 0.8, 'PNG');
        $pdf->Image('images/pnp-finance-logo.png', $paperWidth * 0.735, 0.72, 0.8, 0.8, 'PNG');
        $pdf->SetFont($this->fontArial, '', 10);
        $pdf->Cell(0, 0, 'Republic of the Philippines', 0, 1, 'C');
        $pdf->Cell(0, 0, 'NATIONAL POLICE COMMISSION', 0, 1, 'C');
        $pdf->SetFont($this->fontArialBold, 'B', 10);
        $pdf->Cell(0, 0, 'PHILIPPINE NATIONAL POLICE, FINANCE SERVICE', 0, 1, 'C');
        $pdf->Cell(0, 0, 'REGIONAL FINANCE UNIT CORDILLERA', 0, 1, 'C');
        $pdf->SetFont($this->fontArial, '', 10);
        $pdf->Cell(0, 0, 'Camp Bado Dangwa, La Trinidad, Benguet', 0, 1, 'C');

        if ($hasEmail) {
            $pdf->SetFont($this->fontArial, '', 7.5);
            $pdf->writeHTMLCell(
                0, 0, $pdf->getX(), $pdf->getY(), 'Email Add: <a>rfso15@fs.pnp.gov.ph</a>/Tel (074) 422-3225', 0, 1,
                align: 'C'
            );
            $pdf->SetFont($this->fontArial, '', 10);
        }

        $pdf->Ln(0.3);
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

        $orData = [];

        foreach ($categories as $category) {
            foreach ($category->particulars ?? [] as $parKey => $particular) {
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

                            $orData[$particular->id][] = (Object) [
                                'orDate'            => $orDate,
                                'orNo'              => $orNo,
                                'payorName'         => $payorName,
                                'natureCollection'  => $natureCollection,
                                'amount'            => $amount,
                                'amountInWords'     => $amountInWords,
                                'paymentMode'       => $paymentMode,
                                'draweeBank'        => $draweeBank,
                                'checkNo'           => $checkNo,
                                'checkDate'         => $checkDate,
                                'personnelId'       => $or->accountablePersonnel->id,
                                'personnelName'     => $personnelName,
                                'isCancelled'       => $isCancelled
                            ];
                        }
                    }
                }
            }
        }

        foreach ($orData as $ors) {

            // Main Page
            $pdf->AddPage();

            $columnCounter = 1;

            foreach ($ors as $orKey => $or) {
                $pdf->SetY($pdf->getPageHeight() * 0.077);

                if ($columnCounter === 2) {
                    $pdf->SetX(($pdf->getPageWidth() / 2) + ($pdf->getPageWidth() * 0.00242));
                } else if ($columnCounter === 1) {
                    $pdf->SetX($pdf->getPageWidth() * 0.00605);
                }

                $currentX = $pdf->GetX();
                $currentY = $pdf->GetY();

                $this->generateOfficialReceiptSegment(
                    $pdf, $currentX, $currentY, $orDimension[1], $orDimension[0],
                    hasTemplate: true,
                    orDate: $or->orDate,
                    orNo: $or->orNo,
                    payorName: $or->payorName,
                    natureCollection: $or->natureCollection,
                    amount: $or->amount,
                    amountInWords: $or->amountInWords,
                    paymentMode: $or->paymentMode,
                    draweeBank: $or->draweeBank,
                    checkNo: $or->checkNo,
                    checkDate: $or->checkDate,
                    personnelId: $or->personnelId,
                    personnelName: $or->personnelName,
                    isCancelled: $or->isCancelled
                );

                if ($columnCounter === 2) {
                    if ($orKey < count($ors) - 1) $pdf->AddPage();

                    $columnCounter = 1;
                } else if ($columnCounter === 1) {
                    $columnCounter++;
                }
            }
        }

        $pdfBlob = $pdf->Output($filename, 'S');
        $pdfBase64 = base64_encode($pdfBlob);

        return response()->json([
            'data' => [
                'filename' => $filename,
                'pdf' => $pdfBase64,
                'data' => $orData,
                'success' => 1
            ]
        ], 201)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Content-Type', 'application/json');
    }

    private function printReportCollectionFirearmsReg(
        $printData
    ) : JsonResponse
    {
        $printData = json_decode($printData);

        $categories = $printData->categories ?? [];
        $depositHeaders = $printData->deposit_data->headers ?? [];
        $deposits = $printData->deposit_data->deposits ?? [];

        $certDate = $printData->cert_date;
        $dimension = [
            $printData->paper_dimensions->width,
            $printData->paper_dimensions->height
        ];

        $position = $printData->user_position;
        $designation = $printData->user_designation;
        $station = $printData->user_station;
        $fullName = $printData->user_name;

        $certifiedCorrectName = $printData->cert_correct_name;
        $certifiedCorrectPosition = $printData->cert_correct_position;
        $certifiedCorrectDesignation = $printData->cert_correct_designation;

        $notedByName = $printData->noted_by_name;
        $notedByPosition = $printData->noted_by_position;
        $notedByDesignation = $printData->noted_by_designation;
        $notedByStation = $printData->noted_by_station;

        $grandTotalAmount = $printData->grand_total_amount;
        $grandOrCountTotal = $printData->grand_or_count_total;

        $docTitle = $printData->doc_title;
        $filename = $printData->filename;

        // Initiate PDF and configs
        $pdf = new TCPDF('P', 'in', $dimension);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(env('APP_NAME'));
        $pdf->SetTitle($docTitle);
        $pdf->SetSubject('Report of Collection');
        $pdf->SetMargins(0.4, 0.7, 0.4);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(TRUE, 0.4);

        $pdf->AddPage();

        $paperWidth = $pdf->getPageWidth();
        $paperWidthWithMargin = $pdf->getPageWidth() - 0.8;

        $this->generateHeaderSection($pdf, true);

        $pdf->SetFont($this->fontArialBold, 'B', 14);
        $pdf->Cell(0, 0, 'REPORT OF COLLECTION AND DEPOSITS', 0, 1, 'C');
        $pdf->SetFont($this->fontArialBold, 'B', 12);
        $pdf->Cell(0, 0, 'CSG Caravan on Firearms Registration', 0, 1, 'C');
        $pdf->Ln(0.2);
        $pdf->SetFont($this->fontArialBold, 'BU', 12);
        $pdf->Cell(
            0, 0, "For the Month of $certDate",
            0, 1, 'C'
        );
        $pdf->Ln(0.2);

        foreach ($categories as $catKey => $category) {
            $totalAmount = !empty($category->total_amount) ? $category->total_amount : '-';
            $orCountTotal = !empty($category->or_count_total) ? $category->or_count_total : 0;

            if ($catKey === 0) {
                $pdf->SetFont($this->fontArialBold, 'B', 12);

                $tableHeaderColor = '#e3eeda';
                $htmlTable = '<table border="1" cellpadding="2"><tr>
                    <td
                        width="21.1%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >Particular</td>
                    <td
                        width="28.67%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".';font-size:11px;"
                    >Volume of Official Receipts used</td>
                    <td
                        width="21.97%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >Amount Paid</td>
                    <td
                        width="10.13%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".';font-size:10px;"
                    >Cancelled</td>
                    <td
                        width="18.13%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >TOTAL Amount</td>
                </tr></table>';

                $pdf->writeHTML($htmlTable, ln: false);
            }

            $htmlTable = '<table border="1" cellpadding="2">';

            foreach ($category->particulars ?? [] as $parKey => $particular) {
                $particularName = $particular->particular_name;
                $orCount = $particular->or_count_not_discounted;
                $orAmountPerTrans = $particular->amount_per_transaction_not_discounted;
                $orAmountSum = $particular->amount_sum_not_discounted;
                $orAmountCancelledSum =
                    $particular->cancelled_amount_sum_not_discounted === 0 ? '' :
                    number_format($particular->cancelled_amount_sum_not_discounted, 2);

                $htmlTable .= '<tr>';
                $htmlTable .= '
                <td
                    width="21.1%"
                    align="left"
                    style="font-family:helvetica;font-weight:bold;vertical-align:middle;"
                >'.$particularName.'</td>
                <td
                    width="28.67%"
                    align="center"
                >'.$orCount.'</td>
                <td
                    width="21.97%"
                    align="center"
                >'.$orAmountPerTrans.'</td>
                <td
                    width="10.13%"
                    align="center"
                >'.$orAmountCancelledSum.'</td>
                <td
                    width="18.13%"
                    align="right"
                >'.$orAmountSum.'</td>';
                $htmlTable .= '</tr>';

                foreach ($particular->discounts ?? [] as $discount) {
                    $label = $discount->label;
                    $discountOrCount = $discount->or_count;
                    $discountAmountSum = $discount->amount_sum;
                    $discountAmountTrans = $discount->amount_per_transaction;
                    $discountAmountCancelledSum = $discount->cancelled_amount_sum;

                    $htmlTable .= '<tr>';
                    $htmlTable .= '
                    <td
                        width="21.1%"
                        align="left"
                        style="font-size:10px;"
                    >'.$label.'</td>
                    <td
                        width="28.67%"
                        align="center"
                    >'.$discountOrCount.'</td>
                    <td
                        width="21.97%"
                        align="center"
                    >'.$discountAmountTrans.'</td>
                    <td
                        width="10.13%"
                        align="center"
                    >'.$discountAmountCancelledSum.'</td>
                    <td
                        width="18.13%"
                        align="right"
                    >'.$discountAmountSum.'</td>';
                    $htmlTable .= '</tr>';
                }
            }

            $htmlTable .= '</table>';

            $pdf->SetFont($this->fontArial, '', 12);
            $pdf->writeHTML($htmlTable, ln: false);
        }

        if (count($categories) > 0) {
            $htmlTable = '<table border="1" cellpadding="2"><tr>
                <td
                    width="21.1%"
                    align="center"
                >TOTAL</td>
                <td
                    width="28.67%"
                    align="center"
                    style="text-decoration:underline"
                >'.$grandOrCountTotal.'</td>
                <td
                    width="21.97%"
                    align="center"
                ></td>
                <td
                    width="10.13%"
                    align="center"
                ></td>
                <td
                    width="18.13%"
                    align="right"
                    style="text-decoration:underline"
                >'.$grandTotalAmount.'</td>
            </tr></table>';

            $pdf->SetFont($this->fontArialBold, 'B', 12);
            $pdf->writeHTML($htmlTable, ln: false);

            if (count($depositHeaders) > 0 && count($deposits)) {
                $pdf->Ln(0.3);

                $htmlTable = '<table border="1" cellpadding="2"><tr>
                    <td
                        width="21.1%"
                        align="center"
                        style="background-color:#e3eeda"
                    >Date of Deposit</td>';

                foreach ($depositHeaders as $dHeadKey => $dHeader) {
                    $htmlTable .= '
                    <td
                        width="'.((100-21.1) / count($depositHeaders)).'%"
                        align="center"
                        style="background-color:#e3eeda;"
                    >'.($dHeadKey === count($depositHeaders) - 1 ?
                        'Total Deposit' : $dHeader->particular_name).'</td>';
                }

                $htmlTable .= '</tr></table>';

                $pdf->SetFont($this->fontArialBold, 'B', 10);
                $pdf->writeHTML($htmlTable, ln: false);

                $htmlTable = '<table border="1" cellpadding="2">';

                foreach ($deposits as $deposit) {
                    $dateLabel = $deposit->date;
                    $totalDeposit = $deposit->total_deposits ? number_format($deposit->total_deposits, 2) : '';

                    $htmlTable .= '<tr>';
                    $htmlTable .= '
                    <td
                        width="21.1%"
                        align="center"
                        style="font-size:11px;"
                    >'.$dateLabel.'</td>';

                    foreach ($deposit->deposits as $dItem) {
                        $amount = $dItem->amount ? number_format($dItem->amount, 2) : '';
                        $htmlTable .= '
                        <td
                            width="'.((100-21.1) / count($depositHeaders)).'%"
                            align="right"
                        >'.$amount.'</td>';
                    }

                    $htmlTable .= '
                    <td
                        width="'.((100-21.1) / count($depositHeaders)).'%"
                        align="right"
                    >'.$totalDeposit.'</td>';

                    $htmlTable .= '</tr>';
                }

                $htmlTable .= '</table>';

                $pdf->SetFont($this->fontArial, '', 12);
                $pdf->writeHTML($htmlTable, ln: false);

                $htmlTable = '<table border="1" cellpadding="2"><tr>
                    <td
                        width="21.1%"
                        align="center"
                    >TOTAL</td>';

                foreach ($depositHeaders as $dHeader) {
                    $htmlTable .= '
                    <td
                        width="'.((100-21.1) / count($depositHeaders)).'%"
                        align="right"
                        style="text-decoration:underline"
                    >'.($dHeader->grand_total_amount ? number_format($dHeader->grand_total_amount, 2) : '').'</td>';
                }

                $htmlTable .= '</tr></table>';

                $pdf->SetFont($this->fontArialBold, 'B', 13);
                $pdf->writeHTML($htmlTable, ln: false);
            }

            $pdf->Ln(0.4);

            // Page break
            if (($pdf->getY() / $pdf->getPageHeight()) * 100 > 85) {
                $pdf->AddPage();
            }

            $pdf->SetFont($this->fontArial, '', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, 'Prepared by:', 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3.5, 0, '', 0, 0, 'L');
            $pdf->Cell(0, 0, 'Certified Correct:', 0, 1, 'L');
            $pdf->Ln(0.3);

            $pdf->SetFont($this->fontArialBold, 'BU', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, "$position $fullName", 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3.5, 0, '', 0, 0, 'L');
            $pdf->Cell(0, 0, "$certifiedCorrectPosition $certifiedCorrectName", 0, 1, 'L');

            $pdf->SetFont($this->fontArial, '', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, $designation, 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3.5, 0, '', 0, 0, 'L');
            $pdf->Cell(0, 0, $certifiedCorrectDesignation, 0, 1, 'L');

            $pdf->Ln(0.5);

            // Page break
            if (($pdf->getY() / $pdf->getPageHeight()) * 100 > 85) {
                $pdf->AddPage();
            }

            $pdf->Cell($paperWidthWithMargin / 3, 0, '', 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3, 0, 'Noted by:', 0, 0, 'L');
            $pdf->Cell(0, 0, '', 0, 1, 'L');
            $pdf->Ln(0.3);

            $pdf->SetFont($this->fontArialBold, 'BU', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, '', 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3, 0, "$notedByPosition $notedByName", 0, 0, 'L');
            $pdf->Cell(0, 0, '', 0, 1, 'L');

            $pdf->SetFont($this->fontArial, '', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, '', 0, 0, 'L');
            $pdf->Cell(
                $paperWidthWithMargin / 3, 0,
                $notedByStation ? "$notedByDesignation, $notedByStation" : $notedByDesignation,
                0, 0, 'L');
            $pdf->Cell(0, 0, '', 0, 1, 'L');
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

    private function printReportCollectionCrame(
        $printData
    ) : JsonResponse
    {
        $printData = json_decode($printData);

        $categories = $printData->categories ?? [];

        $certDate = $printData->cert_date;
        $dimension = [
            $printData->paper_dimensions->width,
            $printData->paper_dimensions->height
        ];

        $position = $printData->user_position;
        $designation = $printData->user_designation;
        $station = $printData->user_station;
        $fullName = $printData->user_name;

        $certifiedCorrectName = $printData->cert_correct_name;
        $certifiedCorrectPosition = $printData->cert_correct_position;
        $certifiedCorrectDesignation = $printData->cert_correct_designation;

        $notedByName = $printData->noted_by_name;
        $notedByPosition = $printData->noted_by_position;
        $notedByDesignation = $printData->noted_by_designation;
        $notedByStation = $printData->noted_by_station;

        $grandTotalAmount = $printData->grand_total_amount;
        $grandOrCountTotal = $printData->grand_or_count_total;

        $docTitle = $printData->doc_title;
        $filename = $printData->filename;

        // Initiate PDF and configs
        $pdf = new TCPDF('P', 'in', $dimension);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(env('APP_NAME'));
        $pdf->SetTitle($docTitle);
        $pdf->SetSubject('Report of Collection');
        $pdf->SetMargins(0.4, 0.7, 0.4);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(TRUE, 0.4);

        $pdf->AddPage();

        $paperWidth = $pdf->getPageWidth();
        $paperWidthWithMargin = $pdf->getPageWidth() - 0.8;

        $this->generateHeaderSection($pdf, true);

        $pdf->SetFont($this->fontArialBold, 'B', 12);
        $pdf->Cell(0, 0, 'REPORT OF COLLECTION', 0, 1, 'C');
        $pdf->SetFont($this->fontArialBold, 'BU', 12);
        $pdf->Cell(
            0, 0, $certDate,
            0, 1, 'C'
        );
        $pdf->Ln(0.2);

        $isFirstCategory = true;

        foreach ($categories as $catKey => $category) {
            $rowSpan = count($category->particulars ?? []);
            $totalAmount = !empty($category->total_amount) ? $category->total_amount : '-';
            $orCountTotal = !empty($category->or_count_total) ? $category->or_count_total : 0;

            if ($rowSpan > 0) {
                $pdf->SetFont($this->fontArialNarrowBold, 'B', 12);
                $pdf->Cell(0, 0, strtoupper($category->category_name), $isFirstCategory ? 0 : 'LR', 1, 'L');

                $isFirstCategory = false;
                $tableHeaderColor = '#e3eeda';
                $htmlTable = '<table border="1" cellpadding="2"><tr>
                    <td
                        width="15%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >Office/Unit</td>
                    <td
                        width="27.7%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >Kinds of Collection/Service</td>
                    <td
                        width="15%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >Cost Per Transaction</td>
                    <td
                        width="13.6%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >Total Nos. of Transaction</td>
                    <td
                        width="28.7%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >Total Collections</td>
                </tr></table>';

                $pdf->writeHTML($htmlTable, ln: false);
                $pdf->SetFont($this->fontArialNarrow, '', 10);

                $htmlTable = '<table border="1" cellpadding="2">';

                foreach ($category->particulars ?? [] as $parKey => $particular) {
                    $particularName = $particular->particular_name;
                    $orCount = $particular->or_count;
                    $orAmountPerTrans = $particular->amount_per_transaction;
                    $orAmountSum = $particular->amount_sum;

                    $htmlTable .= '<tr>';
                    $htmlTable .= ($parKey === 0 ? '
                    <td
                        width="15%"
                        align="center"
                        rowspan="'.$rowSpan.'"
                        style="font-family:helvetica;font-weight:bold;vertical-align:middle;"
                    ></td>' : '') . '
                    <td
                        width="27.7%"
                        align="left"
                    >'.$particularName.'</td>
                    <td
                        width="15%"
                        align="right"
                    >'.$orAmountPerTrans.'</td>
                    <td
                        width="13.6%"
                        align="center"
                    >'.$orCount.'</td>
                    <td
                        width="28.7%"
                        align="right"
                    >'.$orAmountSum.'</td>';
                    $htmlTable .= '</tr>';
                }

                $htmlTable .= '</table>';

                $lastY = $pdf->GetY();

                $pdf->writeHTML($htmlTable, ln: false);

                $originalX = $pdf->GetX();
                $originalY = $pdf->GetY();

                $pdf->setXY($pdf->GetX(), $lastY);

                $pdf->SetFont($this->fontArialBold, 'B', 10);
                $pdf->Cell(
                    $paperWidthWithMargin * 0.15,
                    $originalY - $lastY,
                    'RFU-COR',
                    border: 0,
                    align: 'C'
                );
                $pdf->SetFont($this->fontArialNarrow, '', 10);

                // Reset XY
                $pdf->setXY($originalX, $originalY);

                $tableFooterColor = '#e3eeda';
                $htmlTable = '<table border="1" cellpadding="2"><tr>
                    <td
                        width="15%"
                        align="center"
                        style="'."background-color: $tableFooterColor".'"
                    >Sub Total</td>
                    <td
                        width="27.7%"
                        align="center"
                        style="'."background-color: $tableFooterColor".'"
                    ></td>
                    <td
                        width="15%"
                        align="center"
                        style="'."background-color: $tableFooterColor".'"
                    ></td>
                    <td
                        width="13.6%"
                        align="center"
                        style="'."background-color: $tableFooterColor".'"
                    >'.$orCountTotal.'</td>
                    <td
                        width="28.7%"
                        align="right"
                        style="'."background-color: $tableFooterColor".'"
                    >'.$totalAmount.'</td>
                </tr></table>';

                $pdf->SetFont($this->fontArialNarrowBold, 'B', 16);
                $pdf->writeHTML($htmlTable, ln: false);
            }
        }

        if (count($categories) > 0) {
            $tableFooterColor = '#e3eeda';
            $htmlTable = '<table border="1" cellpadding="2"><tr>
                <td
                    width="57.7%"
                    align="left"
                    style="'."background-color: $tableFooterColor".'"
                >GRAND TOTAL</td>
                <td
                    width="13.6%"
                    align="center"
                    style="'."background-color: $tableFooterColor".'"
                >'.$grandOrCountTotal.'</td>
                <td
                    width="28.7%"
                    align="right"
                    style="'."background-color: $tableFooterColor".'"
                >'.$grandTotalAmount.'</td>
            </tr></table>';

            $pdf->SetFont($this->fontArialNarrowBold, 'B', 16);
            $pdf->writeHTML($htmlTable, ln: false);

            $pdf->Ln(0.4);

            // Page break
            if (($pdf->getY() / $pdf->getPageHeight()) * 100 > 85) {
                $pdf->AddPage();
            }

            $pdf->SetFont($this->fontArial, '', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, 'Prepared by:', 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3.5, 0, '', 0, 0, 'L');
            $pdf->Cell(0, 0, 'Certified Correct:', 0, 1, 'L');
            $pdf->Ln(0.3);

            $pdf->SetFont($this->fontArialBold, 'BU', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, "$position $fullName", 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3.5, 0, '', 0, 0, 'L');
            $pdf->Cell(0, 0, "$certifiedCorrectPosition $certifiedCorrectName", 0, 1, 'L');

            $pdf->SetFont($this->fontArial, '', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, $designation, 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3.5, 0, '', 0, 0, 'L');
            $pdf->Cell(0, 0, $certifiedCorrectDesignation, 0, 1, 'L');

            $pdf->Ln(0.5);

            // Page break
            if (($pdf->getY() / $pdf->getPageHeight()) * 100 > 85) {
                $pdf->AddPage();
            }

            $pdf->Cell($paperWidthWithMargin / 3, 0, '', 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3, 0, 'Noted by:', 0, 0, 'L');
            $pdf->Cell(0, 0, '', 0, 1, 'L');
            $pdf->Ln(0.3);

            $pdf->SetFont($this->fontArialBold, 'BU', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, '', 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3, 0, "$notedByPosition $notedByName", 0, 0, 'L');
            $pdf->Cell(0, 0, '', 0, 1, 'L');

            $pdf->SetFont($this->fontArial, '', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, '', 0, 0, 'L');
            $pdf->Cell(
                $paperWidthWithMargin / 3, 0,
                $notedByStation ? "$notedByDesignation, $notedByStation" : $notedByDesignation,
                0, 0, 'L');
            $pdf->Cell(0, 0, '', 0, 1, 'L');
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

    private function printReportCollectionCoaAccounting(
        $printData
    )
    {
        $printData = json_decode($printData);
        $categories = $printData->categories ?? [];

        $certDate = $printData->cert_date;
        $dimension = [
            $printData->paper_dimensions->width,
            $printData->paper_dimensions->height
        ];

        $position = $printData->user_position;
        $designation = $printData->user_designation;
        $station = $printData->user_station;
        $fullName = $printData->user_name;

        $certifiedCorrectName = $printData->cert_correct_name;
        $certifiedCorrectPosition = $printData->cert_correct_position;
        $certifiedCorrectDesignation = $printData->cert_correct_designation;

        $notedByName = $printData->noted_by_name;
        $notedByPosition = $printData->noted_by_position;
        $notedByDesignation = $printData->noted_by_designation;
        $notedByStation = $printData->noted_by_station;

        $grandTotalAmount = $printData->grand_total_amount;
        $grandOrCountTotal = $printData->grand_or_count_total;

        $docTitle = $printData->doc_title;
        $filename = $printData->filename;

        // Initiate PDF and configs
        $pdf = new TCPDF('P', 'in', $dimension);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(env('APP_NAME'));
        $pdf->SetTitle($docTitle);
        $pdf->SetSubject('Report of Collection');
        $pdf->SetMargins(0.4, 0.7, 0.4);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(TRUE, 0.4);

        $pdf->AddPage();

        $paperWidth = $pdf->getPageWidth();
        $paperWidthWithMargin = $pdf->getPageWidth() - 0.8;

        $this->generateHeaderSection($pdf);

        $pdf->SetFont($this->fontArial, '', 12);
        $pdf->Cell(0, 0, 'REPORT OF COLLECTION', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->MultiCell(
            0, 0,
            "For the Month of <strong>$certDate</strong>",
            0, 'C', ln: 1, ishtml: true
        );
        $pdf->Ln(0.2);

        $isFirstCategory = true;

        foreach ($categories as $catKey => $category) {
            $particularCount = count($category->particulars ?? []);
            $totalAmount = !empty($category->total_amount) ? $category->total_amount : '-';
            $orCountTotal = !empty($category->or_count_total) ? $category->or_count_total : '0';

            if ($particularCount > 0) {
                $pdf->SetFont($this->fontArialBold, 'B', 12);
                $pdf->Cell(0, 0, strtoupper($category->category_name), 0, 1, 'L');

                $tableHeaderColor = $isFirstCategory ? '#9aba59' : '#fff';
                $isFirstCategory = false;
                $htmlTable = '<table border="1" cellpadding="2"><tr>
                    <td
                        width="43.4%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >PARTICULARS</td>
                    <td
                        width="20.6%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >NR OF OR USED</td>
                    <td
                        width="18%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >AMOUNT</td>
                    <td
                        width="18%"
                        align="center"
                        style="'."background-color: $tableHeaderColor".'"
                    >REMARKS</td>
                </tr></table>';

                $pdf->writeHTML($htmlTable, ln: false);

                $pdf->SetFont($this->fontArial, '', 10);

                $htmlTable = '<table border="1" cellpadding="2">';

                foreach ($category->particulars ?? [] as $particular) {
                    $particularName = $particular->particular_name;
                    $orCount = $particular->or_count;
                    $orAmountSum = $particular->amount_sum;
                    $remarks = $particular->remarks;

                    $htmlTable .= '<tr>';
                    $htmlTable .= '
                    <td
                        width="43.4%"
                        align="left"
                    >'.$particularName.'</td>
                    <td
                        width="20.6%"
                        align="center"
                    >'.$orCount.'</td>
                    <td
                        width="18%"
                        align="right"
                    >'.$orAmountSum.'</td>
                    <td
                        width="18%"
                        align="left"
                    >'.$remarks.'</td>';
                    $htmlTable .= '</tr>';
                }

                $htmlTable .= '</table>';

                $pdf->writeHTML($htmlTable, ln: false);

                $tableFooterColor = '#dbe5f1';
                $htmlTable = '<table border="1" cellpadding="2"><tr>
                    <td
                        width="43.4%"
                        align="center"
                        style="'."background-color: $tableFooterColor".'"
                    >TOTAL</td>
                    <td
                        width="20.6%"
                        align="center"
                        style="'."background-color: $tableFooterColor".'"
                    >'.$orCountTotal.'</td>
                    <td
                        width="18%"
                        align="right"
                        style="'."background-color: $tableFooterColor".'"
                    >'.$totalAmount.'</td>
                    <td
                        width="18%"
                        align="center"
                        style="'."background-color: $tableFooterColor".'"
                    ></td>
                </tr></table>';

                $pdf->SetFont($this->fontArialBold, 'B', 11);
                $pdf->writeHTML($htmlTable, ln: false);
            }
        }

        if (count($categories) > 0) {
            $tableFooterColor = '#feff01';
            $htmlTable = '<table border="1" cellpadding="2"><tr>
                <td
                    width="43.4%"
                    align="left"
                    style="'."background-color: $tableFooterColor".'"
                >GRAND TOTAL</td>
                <td
                    width="20.6%"
                    align="center"
                    style="'."background-color: $tableFooterColor".'"
                >'.$grandOrCountTotal.'</td>
                <td
                    width="18%"
                    align="right"
                    style="'."background-color: $tableFooterColor".'"
                >'.$grandTotalAmount.'</td>
                <td
                    width="18%"
                    align="center"
                    style="'."background-color: $tableFooterColor".'"
                ></td>
            </tr></table>';

            $pdf->SetFont($this->fontArialBold, 'B', 12);
            $pdf->writeHTML($htmlTable, ln: false);

            $pdf->Ln(0.4);

            // Page break
            if (($pdf->getY() / $pdf->getPageHeight()) * 100 > 85) {
                $pdf->AddPage();
            }

            $pdf->SetFont($this->fontArial, '', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, 'Prepared by:', 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3.5, 0, '', 0, 0, 'L');
            $pdf->Cell(0, 0, 'Certified Correct:', 0, 1, 'L');
            $pdf->Ln(0.3);

            $pdf->SetFont($this->fontArialBold, 'BU', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, "$position $fullName", 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3.5, 0, '', 0, 0, 'L');
            $pdf->Cell(0, 0, "$certifiedCorrectPosition $certifiedCorrectName", 0, 1, 'L');

            $pdf->SetFont($this->fontArial, '', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, $designation, 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3.5, 0, '', 0, 0, 'L');
            $pdf->Cell(0, 0, $certifiedCorrectDesignation, 0, 1, 'L');

            $pdf->Ln(0.5);

            // Page break
            if (($pdf->getY() / $pdf->getPageHeight()) * 100 > 85) {
                $pdf->AddPage();
            }

            $pdf->Cell($paperWidthWithMargin / 3, 0, '', 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3, 0, 'Noted by:', 0, 0, 'L');
            $pdf->Cell(0, 0, '', 0, 1, 'L');
            $pdf->Ln(0.3);

            $pdf->SetFont($this->fontArialBold, 'BU', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, '', 0, 0, 'L');
            $pdf->Cell($paperWidthWithMargin / 3, 0, "$notedByPosition $notedByName", 0, 0, 'L');
            $pdf->Cell(0, 0, '', 0, 1, 'L');

            $pdf->SetFont($this->fontArial, '', 12);
            $pdf->Cell($paperWidthWithMargin / 3, 0, '', 0, 0, 'L');
            $pdf->Cell(
                $paperWidthWithMargin / 3, 0,
                $notedByStation ? "$notedByDesignation, $notedByStation" : $notedByDesignation,
                0, 0, 'L');
            $pdf->Cell(0, 0, '', 0, 1, 'L');
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

    private function printReportCollectionData(
        $from,
        $to,
        $categoryIds = [],
        $certifiedCorrectId,
        $notedById,
        $paperSizeId,
        $isPrintPreview = false,
        $template
    ) : JsonResponse | string
    {
        $data = [];
        $dates = $this->generateDateRange($from, $to);
        $certDate = ucwords(strtolower($this->getCertDateRange($from, $to)));
        $data['cert_date'] = $certDate;
        $categories = Category::with(['particulars'])
            ->whereIn('id', $categoryIds)
            ->orderBy('order_no')
            ->get();

        // Get the paper size
        $dimension = $this->getPaperDimensions($paperSizeId);

        $data['paper_dimensions'] = [
            'width' => $dimension[1],
            'height' => $dimension[0]
        ];

        // Get current user
        $user = $this->getCurrentUser();
        $position = strtoupper($user->position);
        $designation = $user->designation;
        $station = strtoupper($user->station);
        $fullName = $user->name;

        $data['user_name'] = $fullName;
        $data['user_position'] = $position;
        $data['user_designation'] = $designation;
        $data['user_station'] = $station;

        // Get Certified Correct Signatory
        $certifiedCorrect = $this->getSignatory($certifiedCorrectId, 'roc_certified_correct');
        $certifiedCorrectName = strtoupper($certifiedCorrect->signatory_name);
        $certifiedCorrectPosition = strtoupper($certifiedCorrect->position);
        $certifiedCorrectDesignation = $certifiedCorrect->designation;

        $data['cert_correct_name'] = $certifiedCorrectName;
        $data['cert_correct_position'] = $certifiedCorrectPosition;
        $data['cert_correct_designation'] = $certifiedCorrectDesignation;

        // Get Noted By Signatory
        $notedBy = $this->getSignatory($notedById, 'roc_noted_by');
        $notedByName = strtoupper($notedBy->signatory_name);
        $notedByPosition = strtoupper($notedBy->position);
        $notedByDesignation = $notedBy->designation;
        $notedByStation = strtoupper($notedBy->station);

        $data['noted_by_name'] = $notedByName;
        $data['noted_by_position'] = $notedByPosition;
        $data['noted_by_designation'] = $notedByDesignation;
        $data['noted_by_station'] = $notedByStation;

        $docTitle = "Report of Collection ($from to $to)";
        $filename = "report_collection_$from".'_'."$to.pdf";

        $data['doc_title'] = $docTitle;
        $data['filename'] = $filename;

        $grandTotalAmount = 0;
        $grandOrCountTotal = 0;

        $catCounter = 0;

        foreach ($categories as $catKey => $category) {
            $totalAmount = 0;
            $orCountTotal = 0;

            $particularIds = collect($category->particulars)->map(function($particular) {
                return $particular['id'];
            });
            $orCountAll = OfficialReceipt::whereIn('nature_collection_id', $particularIds)
                ->whereIn('deposited_date', $dates)
                ->count();

            if ($orCountAll > 0) {
                $data['categories'][$catCounter]['category_name'] = strtoupper($category->category_name);

                $parCounter = 0;

                foreach ($category->particulars ?? [] as $parKey => $particular) {
                    $included = false;
                    $particularObj = Particular::find($particular->id);
                    $orCount = OfficialReceipt::where('nature_collection_id', $particular->id)
                        ->where(function($query) use($dates) {
                            $query->whereIn('deposited_date', $dates)
                                ->orWhereIn('cancelled_date', $dates);
                        })
                        ->count();
                    $orCountNoDiscount = '';
                    $orAmountSum = OfficialReceipt::where('nature_collection_id', $particular->id)
                        ->whereIn('deposited_date', $dates)
                        ->whereNull('cancelled_date')
                        ->sum('amount');
                    $orAmountSumCancelled = '';
                    $orAmountSumNoDiscount = '';
                    $orAmountSumCancelledNoDiscount = '';
                    $orAmountPerTrans = '';
                    $orAmountPerTransNoDiscount = '';
                    $_orCount = '';
                    $_orAmountSum = '';

                    if ($template === 'coa_accounting' || $particularObj->default_amount > 0) {
                        $_orCount = (string) $orCount ?? '-';
                        $orCountNoDiscount = OfficialReceipt::where('nature_collection_id', $particular->id)
                            ->where(function($query) use($dates) {
                                $query->whereIn('deposited_date', $dates)
                                    ->orWhereIn('cancelled_date', $dates);
                            })
                            ->whereNull('discount_id')
                            ->count() ?? '-';
                        $orAmountSumNoDiscount = OfficialReceipt::where('nature_collection_id', $particular->id)
                            ->whereIn('deposited_date', $dates)
                            ->whereNull('discount_id')
                            ->whereNull('cancelled_date')
                            ->sum('amount') ?? '-';
                        $orAmountSumCancelledNoDiscount = OfficialReceipt::where('nature_collection_id', $particular->id)
                            ->whereIn('cancelled_date', $dates)
                            ->whereNull('deposited_date')
                            ->whereNull('discount_id')
                            ->sum('amount');
                        $orAmountSumCancelled = OfficialReceipt::where('nature_collection_id', $particular->id)
                            ->whereIn('cancelled_date', $dates)
                            ->whereNull('deposited_date')
                            ->sum('amount');
                        $_orAmountSum = $orAmountSum ? number_format($orAmountSum, 2) : '-';
                        $orAmountPerTrans = number_format($particularObj->default_amount, 2);
                        $orAmountPerTransNoDiscount = number_format($particularObj->default_amount, 2);
                        $orAmountSumNoDiscount = number_format($orAmountSumNoDiscount, 2);
                    } else {
                        if ($particularObj->default_amount > 0) {
                        } else {
                            $_particularObjs = OfficialReceipt::where('nature_collection_id', $particular->id)
                                ->where(function($query) use($dates) {
                                    $query->whereIn('deposited_date', $dates)
                                        ->orWhereIn('cancelled_date', $dates);
                                })
                                ->orderBy('created_at')
                                ->get();

                            foreach ($_particularObjs as $_parKey => $_particularObj) {
                                $_orCount .= '1';
                                $orCountNoDiscount .= '1';
                                $_orAmountSum .= number_format($_particularObj->amount, 2);
                                $orAmountPerTrans .= number_format($_particularObj->amount, 2);
                                $orAmountPerTransNoDiscount .= number_format($_particularObj->amount, 2);
                                $orAmountSumNoDiscount .= number_format($_particularObj->amount, 2);

                                if ($_particularObj->cancelled_date) {
                                    $orAmountSumCancelledNoDiscount .= number_format($_particularObj->amount, 2);
                                    $orAmountSumCancelled .= number_format($_particularObj->amount, 2);
                                } else {
                                    $orAmountSumCancelledNoDiscount .= '';
                                    $orAmountSumCancelled .= '';
                                }

                                if (count($_particularObjs) > 0) {
                                    if ($_parKey >= 0 && $_parKey !== count($_particularObjs) - 1) {
                                        $_orCount .= '<br/>';
                                        $orCountNoDiscount .= '<br/>';
                                        $_orAmountSum .= '<br/>';
                                        $orAmountPerTrans .= '<br/>';
                                        $orAmountPerTransNoDiscount .= '<br/>';
                                        $orAmountSumNoDiscount .= '<br/>';
                                        $orAmountSumCancelledNoDiscount .= '<br/>';
                                        $orAmountSumCancelled .= '<br/>';
                                    }
                                }
                            }
                        }
                    }

                    $particularDiscounts = [];
                    $discounts = Discount::orderBy('discount_name')->get();

                    foreach ($discounts as $discount) {
                        $discountName = $discount->discount_name;
                        $percentage = round($discount->percent, 2);
                        $orCountDiscounted = OfficialReceipt::where('nature_collection_id', $particular->id)
                            ->where(function($query) use($dates) {
                                $query->whereIn('deposited_date', $dates)
                                    ->orWhereIn('cancelled_date', $dates);
                            })
                            ->where('discount_id', $discount->id)
                            ->count();

                        if (!$orCountDiscounted) {
                            continue;
                        }

                        $orAmountSumDiscounted = OfficialReceipt::where('nature_collection_id', $particular->id)
                            ->whereIn('deposited_date', $dates)
                            ->where('discount_id', $discount->id)
                            ->whereNull('cancelled_date')
                            ->sum('amount');
                        $orAmountSumCancelledDiscounted = OfficialReceipt::where('nature_collection_id', $particular->id)
                            ->whereIn('cancelled_date', $dates)
                            ->whereNull('deposited_date')
                            ->where('discount_id', $discount->id)
                            ->sum('amount');
                        $orAmountPerTransDiscounted = $particularObj->default_amount ?
                            $particularObj->default_amount - ($particularObj->default_amount * ($discount->percent / 100)) :
                            OfficialReceipt::where('nature_collection_id', $particular->id)
                                ->where(function($query) use($dates) {
                                    $query->whereIn('deposited_date', $dates)
                                        ->orWhereIn('cancelled_date', $dates);
                                })
                                ->where('discount_id', $discount->id)
                                ->min('amount');

                        $particularDiscounts[] = [
                            'label' => "*with $discountName discount ($percentage%)",
                            'or_count' => (string) $orCountDiscounted ?? '-',
                            'cancelled_amount_sum' =>
                                $orAmountSumCancelledDiscounted ? number_format($orAmountSumCancelledDiscounted, 2) : '',
                            'amount_sum' =>
                                $orAmountSumDiscounted ? number_format($orAmountSumDiscounted, 2) : '-',
                            'amount_per_transaction' =>
                                $orAmountPerTransDiscounted ? number_format($orAmountPerTransDiscounted, 2) : '-',
                        ];
                    }

                    if ($template === 'coa_accounting') {
                        $included = $particular->coa_accounting;
                    } else if ($template === 'pnp_crame') {
                        $included = $particular->pnp_crame;
                    } else if ($template === 'firearms_registration') {
                        $included = $particular->firearms_registration;
                    }

                    if ($orCount > 0 && $included) {
                        $particularName = $particular->particular_name;
                        $totalAmount += $orAmountSum;
                        $orCountTotal += $orCount;
                        $grandTotalAmount += $orAmountSum;
                        $grandOrCountTotal += $orCount;

                        $data['categories'][$catCounter]['particulars'][$parCounter] = [
                            'particular_name' => $particularName,
                            'or_count' => $_orCount,
                            'or_count_not_discounted' => $orCountNoDiscount,
                            'amount_per_transaction' => $orAmountPerTrans,
                            'amount_per_transaction_not_discounted' => $orAmountPerTransNoDiscount,
                            'amount_sum' => $_orAmountSum,
                            'cancelled_amount_sum' => $orAmountSumCancelled,
                            'amount_sum_not_discounted' => $orAmountSumNoDiscount,
                            'cancelled_amount_sum_not_discounted' => $orAmountSumCancelledNoDiscount,
                            'remarks' => '',
                            'discounts' => $particularDiscounts
                        ];
                        $data['categories'][$catCounter]['total_amount'] =
                            $totalAmount ? number_format($totalAmount, 2) : '-';
                        $data['categories'][$catCounter]['or_count_total'] = (string) $orCountTotal ?? '';

                        $parCounter++;
                    }
                }

                $catCounter++;
            }
        }

        $data['grand_total_amount'] = $grandTotalAmount ? number_format($grandTotalAmount, 2) : '-';
        $data['grand_or_count_total'] = (string) $grandOrCountTotal;

        if ($template === 'firearms_registration') {
            $depositHeaders = [];
            $deposits = [];

            foreach ($categories as $catKey => $category) {
                foreach ($category->particulars ?? [] as $parKey => $particular) {
                    if ($particular->firearms_registration) {
                        $account = Account::find($particular->account_id);
                        $depositHeaders[] = [
                            'id' => $particular->id,
                            'particular_name' => "$account->account_name<br/>($particular->particular_name)",
                            'grand_total_amount' => 0
                        ];
                    }
                }
            }

            $depositHeaders[] = [
                'id' => '',
                'particular_name' => '',
                'grand_total_amount' => 0
            ];

            $depositCounter = 0;

            foreach ($dates as $dateKey => $date) {
                $tempDeposits = [];
                $totalDeposits = 0;

                foreach ($depositHeaders as $headKey => $header) {
                    if (!empty($header['id'])) {
                        $orAmount = OfficialReceipt::where('nature_collection_id', $header['id'])
                            ->where('deposited_date', $date)
                            ->sum('amount');
                        $totalDeposits += $orAmount;
                        $tempDeposits[] = [
                            'particular_id' => $header['id'],
                            'amount' => $orAmount
                        ];

                        $depositHeaders[$headKey]['grand_total_amount'] += $orAmount;
                    } else {
                        $depositHeaders[$headKey]['grand_total_amount'] += $totalDeposits;
                    }
                }

                if ($totalDeposits > 0) {
                    $deposits[$depositCounter]['date'] = date('F d, Y', strtotime($date));
                    $deposits[$depositCounter]['deposits'] = $tempDeposits;
                    $deposits[$depositCounter]['total_deposits'] = $totalDeposits;
                    $depositCounter++;
                }
            }

            $data['deposit_data'] = [
                'headers' => $depositHeaders,
                'deposits' => $deposits
            ];
        }

        if ($isPrintPreview) {
            return response()->json([
                'data' => [
                    'data' => $data,
                    'success' => 1
                ]
            ], 201)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Content-Type', 'application/json');
        }

        return json_encode($data);
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
        $certDate = $this->getCertDateRange($from, $to);
        $categories = Category::with(['particulars' => function($query) use($particularsIds) {
                $query->whereIn('id', $particularsIds);
            }])
            ->orderBy('order_no')
            ->get();

        // Get the paper size
        $dimension = $this->getPaperDimensions($paperSizeId);

        // Get current user
        $user = $this->getCurrentUser();
        $position = strtoupper($user->position);
        $designation = $user->designation;
        $station = strtoupper($user->station);
        $fullName = $user->name;

        // Get Certified Correct Signatory
        $certifiedCorrect = $this->getSignatory($certifiedCorrectId, 'crr_certified_correct');
        $certifiedCorrectName = strtoupper($certifiedCorrect->signatory_name);
        $certifiedCorrectPosition = strtoupper($certifiedCorrect->position);
        $certifiedCorrectDesignation = $certifiedCorrect->designation;

        $docTitle = "Cash Receipt Record ($from to $to)";
        $filename = "cash_receipt_record_$from".'_'."$to.pdf";

        // Initiate PDF and configs
        $pdf = new TCPDF('P', 'in', $dimension);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(env('APP_NAME'));
        $pdf->SetTitle($docTitle);
        $pdf->SetSubject('Cash Receipts Record');
        $pdf->SetMargins(0.4, 0.7, 0.4);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(TRUE, 0.4);

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

                    $pdf->setCellHeightRatio(0.7);
                    $pdf->Cell(0, 0, '', 'LTR', 1, 'C');
                    $pdf->setCellHeightRatio(1.25);
                    $pdf->SetFont($this->fontArialBold, 'B', 16);
                    $pdf->Cell(0, 0, 'CASH RECEIPT RECORD', 'LR', 1, 'C');

                    $pdf->SetFont($this->fontArial, '', 14);
                    $pdf->Cell(0, 0, '', 'LR', 1, 'C');
                    $pdf->Cell(0, 0, 'REGIONAL FINANCE UNIT CORDILLERA', 'LR', 1, 'C');
                    $pdf->setCellHeightRatio(0.2);
                    $pdf->Cell(0, 0, '', 'LR', 1, 'C');
                    $pdf->setCellHeightRatio(1.25);

                    $pdf->SetFont($this->fontArial, '', 10);
                    $pdf->Cell(0, 0, 'Page 1', 'LR', 1, 'R');
                    $pdf->setCellHeightRatio(0.3);
                    $pdf->Cell(0, 0, '', 'LR', 1, 'C');
                    $pdf->setCellHeightRatio(1.25);

                    $pdf->SetFont($this->fontArialBold, 'B', 10);
                    $pdf->Cell($paperWidth * 0.46, 0, "$position $fullName", 1, 0, 'C');
                    $pdf->Cell($paperWidth * 0.31, 0, $designation, 1, 0, 'C');
                    $pdf->Cell(0, 0, $station, 1, 1, 'C');

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

                    // Page break
                    if (($pdf->getY() / $pdf->getPageHeight()) * 100 > 77) {
                        $pdf->AddPage();
                    }

                    $pdf->SetFont($this->fontArialBold, 'B', 12);
                    $pdf->Cell(0, 0.3, 'C E R T I F I C A T I O N', 'LTR', 1, 'C');
                    $pdf->Cell(0, 0.2, '', 'LR', 1, 'C');
                    $pdf->SetFont('helvetica', '', 12);
                    $pdf->MultiCell($paperWidth * 0.07, 1, '', 'L', 'L', 0, 0);
                    $pdf->MultiCell($paperWidth * 0.86, 0.8,
                        "          I hereby certify that the foregoing is a correct and complete record of all\n".
                        "collections and deposits had by me in my capacity as Collecting Officer of Regional\n".
                        "Finance Unit Cordillera during the period from <strong>$certDate</strong> inclusives, as\n".
                        "indicated in the corresponding columns.",
                        0, 'J', 0, 0,
                        ishtml: true
                    );
                    $pdf->MultiCell(0, 1, '', 'R', 'L', 0, 1);
                    $pdf->SetFont($this->fontArialBold, 'B', 12);
                    $pdf->Cell(0, 0.7, strtoupper("$position $fullName              "), 'LBR', 1, 'R');

                    $pdf->Ln(0.7);

                    // Page break
                    if (($pdf->getY() / $pdf->getPageHeight()) * 100 > 85) {
                        $pdf->AddPage();
                    }

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
        $personnelId,
        $personnelName,
        $isCancelled = false
    ) : void
    {
        if ($hasTemplate) {
            $pdf->Image('images/or-template-blue.jpg', $x, $y, $w, $h, 'JPEG');
        }

        if ($hasTemplate) {
            $pdf->SetTextColor(0,110,195);
        } else {
            $pdf->SetTextColor(50, 50, 50);
        }

        $pdf->SetFont('helvetica', 'B', 13);

        // Generate a cell
        $pdf->SetXY($x, $y + 1.77);
        $pdf->Cell(1.6, 0, $orDate, 0, 0, 'R');
        $pdf->Cell(0.5, 0, '', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 18.5);

        if ($hasTemplate) {
            $pdf->SetTextColor(0,110,195);
        } else {
            $pdf->SetTextColor(188,113,136);
        }

        $pdf->SetXY($x + 2.45, $y + 1.65);
        $pdf->Cell(1.4, 0, $hasTemplate ? $orNo : '', 0, 1, 'L');

        if ($hasTemplate) {
            $pdf->SetTextColor(0,110,195);
        } else {
            $pdf->SetTextColor(50, 50, 50);
        }

        $pdf->SetFont('helvetica', 'B', strlen($payorName) > 24 ? 9.5 : 12);

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

        $pdf->SetXY($x + 0.25, $y + 5.19);
        $pdf->Cell(1.6, 0, '', 0, 0, 'L');
        $pdf->Cell(2, 0, $amount, 0, 1, 'R');

        $pdf->SetFont('helvetica', 'B', strlen($amountInWords) >= 35 ? 8 : 11);

        $pdf->SetXY($x + 0.25, $y + (strlen($amountInWords) >= 35 ? 5.62 : 5.66));
        $pdf->MultiCell(3.6, 0, $amountInWords, 0, 'L', 0, 1);

        $pdf->SetFont('zapfdingbats', '', 12);

        switch ($paymentMode) {
            case 'cash':
                $pdf->SetXY($x + 0.29, $y + 6.04);
                $pdf->Cell(1.6, 0, '4', 0, 1, 'L');
                break;
            case 'check':
                $pdf->SetXY($x + 0.29, $y + 6.24);
                $pdf->Cell(1.35, 0, '4', 0, 1, 'L');

                $pdf->SetXY($x + 1.6, $y + 6.28);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->MultiCell(0.73, 0, $draweeBank, 0, 'L', false, 0);
                $pdf->MultiCell(0.75, 0, $checkNo, 0, 'L', false, 0);
                $pdf->MultiCell(0.84, 0, $checkDate, 0, 'L');
                break;
            case 'money_order':
                $pdf->SetXY($x + 0.29, $y + 6.44);
                $pdf->Cell(1.6, 0, '4', 0, 1, 'L');
                break;
            default:
                break;
        }

        $pdf->SetFont('helvetica', 'B', 9);

        $pdf->SetXY($x + 0.25, $y + 7.22);
        $pdf->Cell(1.85, 0, '', 0, 0, 'L');
        $pdf->Cell(1.75, 0, $personnelName, 0, 1, 'C');

        $esig = $this->getUserESignature($personnelId);

        if ($esig && $hasTemplate) {
            $pdf->Image(
                $esig,
                $x + 2.7,
                $pdf->GetY() - 0.6,
                h: 0.6,
                type: 'PNG',
                resize: true,
                dpi: 500
            );
        }

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
        $personnelId = $officialReceipt->accountablePersonnel->id;
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
            personnelId: $personnelId,
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
