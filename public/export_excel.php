<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!is_logged_in()) {
    die('Not authorized');
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $search = $_GET['search'] ?? '';
    $bank_filter = $_GET['bank_filter'] ?? '';
    $biller_filter = $_GET['biller_filter'] ?? '';
    $spec_filter = $_GET['spec_filter'] ?? '';
    $status_filter = $_GET['status_filter'] ?? '';
    
    // Create the SQL query
    $sql = "SELECT 
        b.name as bank_name, 
        b.bank_id,
        bl.name as biller_name, 
        bl.biller_id,
        bs.name as bank_spec,
        bls.name as biller_spec,
        pd.status,
        pd.fee_bank,
        pd.fee_biller,
        pd.fee_rintis,
        pd.fee_included,
        pd.admin_fee,
        GROUP_CONCAT(
            CONCAT(c.name, ' (', DATE_FORMAT(cc.date_live, '%Y-%m-%d'), ')')
            SEPARATOR ', '
        ) as channels
        FROM prima_data pd
        LEFT JOIN banks b ON pd.bank_id = b.id
        LEFT JOIN billers bl ON pd.biller_id = bl.id
        LEFT JOIN specs bs ON pd.bank_spec_id = bs.id
        LEFT JOIN specs bls ON pd.biller_spec_id = bls.id
        LEFT JOIN connection_channels cc ON pd.id = cc.prima_data_id
        LEFT JOIN channels c ON cc.channel_id = c.id
        WHERE 1=1";

    $params = [];

    if (!empty($search)) {
        $sql .= " AND (b.name LIKE :search OR bl.name LIKE :search OR bs.name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if (!empty($bank_filter)) {
        $sql .= " AND pd.bank_id = :bank_id";
        $params[':bank_id'] = $bank_filter;
    }
    
    if (!empty($biller_filter)) {
        $sql .= " AND pd.biller_id = :biller_id";
        $params[':biller_id'] = $biller_filter;
    }
    
    if (!empty($spec_filter)) {
        $sql .= " AND (pd.bank_spec_id = :spec_id OR pd.biller_spec_id = :spec_id)";
        $params[':spec_id'] = $spec_filter;
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND pd.status = :status";
        $params[':status'] = $status_filter;
    }

    // Add GROUP BY and ORDER BY
    $sql .= " GROUP BY pd.id, b.name, b.bank_id, bl.name, bl.biller_id, 
              bs.name, bls.name, pd.status, pd.fee_bank, pd.fee_biller, 
              pd.fee_rintis, pd.fee_included, pd.admin_fee 
              ORDER BY pd.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($data)) {
        die('No data found to export');
    }

    // Create new Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Prima Data Export');

    // Set headers
    $headers = [
        'Bank Name',
        'Bank ID',
        'Biller Name',
        'Biller ID',
        'Bank Spec',
        'Biller Spec',
        'Status',
        'Fee Bank',
        'Fee Biller',
        'Fee Rintis',
        'Fee Status',
        'Admin Fee',
        'Total Fee',
        'Channels'
    ];
    $sheet->fromArray([$headers], NULL, 'A1');

    // Style headers
    $headerStyle = $sheet->getStyle('A1:N1');
    $headerStyle->getFont()->setBold(true);
    $headerStyle->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()
        ->setARGB('FFE0E0E0');

    // Add data
    $row = 2;
    foreach ($data as $item) {
        $total_fee = $item['fee_bank'] + $item['fee_biller'] + $item['fee_rintis'] + $item['admin_fee'];
        $rowData = [
            $item['bank_name'],
            $item['bank_id'],
            $item['biller_name'],
            $item['biller_id'],
            $item['bank_spec'],
            $item['biller_spec'],
            $item['status'],
            number_format($item['fee_bank'], 2),
            number_format($item['fee_biller'], 2),
            number_format($item['fee_rintis'], 2),
            $item['fee_included'] ? 'Include' : 'Exclude',
            number_format($item['admin_fee'], 2),
            number_format($total_fee, 2),
            $item['channels']
        ];
        $sheet->fromArray([$rowData], NULL, "A{$row}");

        $sheet->getStyle("H{$row}:J{$row}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00_);[Red](#,##0.00)');
        $sheet->getStyle("L{$row}:M{$row}")
            ->getNumberFormat()
            ->setFormatCode('#,##0.00_);[Red](#,##0.00)');
        
        // Style inactive status
        if ($item['status'] === 'inactive') {
            $sheet->getStyle("G{$row}")
                ->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFFF9999');
        }
        
        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'N') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = 'prima_data';
    if (!empty($bank_filter)) {
        $filename .= '_bank_' . preg_replace('/[^A-Za-z0-9]/', '', $data[0]['bank_name']);
    }
    if (!empty($biller_filter)) {
        $filename .= '_biller_' . preg_replace('/[^A-Za-z0-9]/', '', $data[0]['biller_name']);
    }
    if (!empty($status_filter)) {
        $filename .= '_' . $status_filter;
    }
    $filename .= '_' . date('Y-m-d_His') . '.xlsx';

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="prima_data_' . date('Y-m-d_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Create Excel writer and output directly to PHP output
    $writer = new Xlsx($spreadsheet);
    ob_end_clean(); // Clean output buffer
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log("Excel export error: " . $e->getMessage());
    die("Error generating Excel file: " . $e->getMessage());
}