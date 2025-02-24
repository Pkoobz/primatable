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

    // Create the SQL query
    $sql = "SELECT 
        b.name as bank_name, 
        b.bank_id,
        bl.name as biller_name, 
        bl.biller_id,
        bs.name as bank_spec,
        bls.name as biller_spec,
        pd.status,
        pd.date_live,
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
        GROUP BY pd.id, b.name, b.bank_id, bl.name, bl.biller_id, 
                bs.name, bls.name, pd.status, pd.date_live
        ORDER BY pd.id DESC";

    $stmt = $pdo->query($sql);
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
        'Date Live',
        'Channels'
    ];
    $sheet->fromArray([$headers], NULL, 'A1');

    // Style headers
    $headerStyle = $sheet->getStyle('A1:I1');
    $headerStyle->getFont()->setBold(true);
    $headerStyle->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()
        ->setARGB('FFE0E0E0');

    // Add data
    $row = 2;
    foreach ($data as $item) {
        $rowData = [
            $item['bank_name'],
            $item['bank_id'],
            $item['biller_name'],
            $item['biller_id'],
            $item['bank_spec'],
            $item['biller_spec'],
            $item['status'],
            $item['date_live'],
            $item['channels']
        ];
        $sheet->fromArray([$rowData], NULL, "A{$row}");
        
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
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

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