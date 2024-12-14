<?php

namespace App\Console\Commands;

use App\Http\Controllers\BigCommerceController;
use App\Models\ImportProduct;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FetchFTPData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fetch-ftp-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Define the FTP file paths
        $filePath1 = '/UnitData-20/Availability20D.csv';
        $filePath2 = '/Signet_12820.csv';
        $bigCommerceController = new BigCommerceController();
        $bigCommerceController->addRequestLogs('job/add-ftp-stock', NULL);
        // Process the Availability20D.csv file
        if (Storage::disk('ftp')->exists($filePath1)) {
            $fileContent1 = Storage::disk('ftp')->get($filePath1);
            $this->processAvailabilityCSV($fileContent1);
        } else {
            $this->error("File $filePath1 not found on FTP server.");
        }

        // Process the Signet_12820.csv file
        if (Storage::disk('ftp')->exists($filePath2)) {
            $fileContent2 = Storage::disk('ftp')->get($filePath2);
            $this->processSignetCSV($fileContent2);
        } else {
            $this->error("File $filePath2 not found on FTP server.");
        }
    }

    /**
     * Process the Availability20D.csv file
     *
     * @param string $fileContent
     * @return void
     */
    public function processAvailabilityCSV($fileContent)
    {
        // Split the CSV content into rows
        $rows = explode("\n", $fileContent);
        $this->info("Inventory CSV: ".count($rows));
        $bigCommerceController = new BigCommerceController();
        $allProducts = [];

        $inventoryProducts = ImportProduct::where('type','quantity')->get();
        foreach ($rows as $row) {
            $columns = str_getcsv($row); // Convert row into an array

            if (count($columns) > 1) {
                $sku = $columns[0];         // Column 1: SKU
                $inventory = $columns[1];   // Column 2: Inventory

                // To store all the products
                $chkProd = $inventoryProducts->where('sku_id', $sku)->first();

                    // Check if the product exists in database
                if (!is_null($chkProd)) {
                    $allProducts[] = [
                        'sku_id' => $chkProd->sku_id,
                        'product_id' => $chkProd->product_id ?? NULL,
                        'quantity' => $inventory ?: 0,
                        'type' => 'quantity',
                        'created_at' => $chkProd->created_at,
                        'updated_at' => now(),
                    ];
                } else {
                    // Fetch product from BigCommerce
                    $bigCommerce = new \App\Library\BigCommerce();
                    $bigCommerceProd = $bigCommerce->getProducts(['sku' => $sku]);

                    if (is_array($bigCommerceProd['data']) && count($bigCommerceProd['data']) > 0) {
                        $productId = $bigCommerceProd['data'][0]['id'];

                        $allProducts[] = [
                            'sku_id' => $sku,
                            'product_id' => $productId,
                            'quantity' => $inventory ?: 0,
                            'type' => 'quantity',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    else if(!is_array($bigCommerceProd['data'])){
                        $bigCommerceController->addRequestLogs('job/get-bc-product', NULL);
                    }
                }
                if (count($allProducts) >= 100) {
                    DB::table('import_products')->upsert(
                        $allProducts,
                        ['sku_id'],
                        ['product_id', 'quantity','type', 'created_at', 'updated_at']
                    );

                    $allProducts = [];
                }
            }
        }
    }

    /**
     * Process the Signet_12820.csv file
     *
     * @param string $fileContent
     * @return void
     */
    public function processSignetCSV($fileContent)
    {
        // Split the CSV content into rows
        $rows = explode("\n", $fileContent);
        $this->info("Cost CSV: ".count($rows));
        $bigCommerceController = new BigCommerceController();
        $allProducts = [];
        $inventoryProducts = ImportProduct::where('type','price')->get();

        foreach ($rows as $row) {
            $columns = str_getcsv($row); // Convert row into an array

            if (count($columns) > 2) {
                $sku = $columns[1];       // Column 2: SKU
                $costPrice =  (($columns[2] * 1.2) + 4) * 1.25;

                // To store all the products
                $chkProd = $inventoryProducts->where('sku_id', $sku)->first();

                    // Check if the product exists in database
                if (!is_null($chkProd)) {
                    $allProducts[] = [
                        'sku_id' => $chkProd->sku_id,
                        'product_id' => $chkProd->product_id ?? NULL,
                        'price' => $costPrice,
                        'type' => 'price',
                        'created_at' => $chkProd->created_at,
                        'updated_at' => now(),
                    ];
                } else {
                    // Fetch product from BigCommerce
                    $bigCommerce = new \App\Library\BigCommerce();
                    $bigCommerceProd = $bigCommerce->getProducts(['sku' => $sku]);

                    if (is_array($bigCommerceProd['data']) && count($bigCommerceProd['data']) > 0) {
                        $productId = $bigCommerceProd['data'][0]['id'];

                        $allProducts[] = [
                            'sku_id' => $sku,
                            'product_id' => $productId,
                            'price' => $costPrice,
                            'type' => 'price',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    else if(!is_array($bigCommerceProd['data'])){
                        $bigCommerceController->addRequestLogs('job/get-bc-product', NULL);
                    }
                }
                if (count($allProducts) >= 100) {
                    DB::table('import_products')->upsert(
                        $allProducts,
                        ['sku_id'],
                        ['product_id', 'price','type', 'created_at', 'updated_at']
                    );

                    $allProducts = [];
                }
            }
        }
    }
}
