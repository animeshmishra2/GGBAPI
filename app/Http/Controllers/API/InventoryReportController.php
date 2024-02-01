<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    public function get_inventory_report(Request $request)
    {
        
        ini_set('max_execution_time', 14000);
        $start_date =  !empty($_GET['start_date']) ? $_GET['start_date'] : null;
        $end_date = !empty($_GET['end_date'])? $request->end_date :  null;
        $limit = !empty($_GET['rows']) ? $_GET['rows'] : 10;
        $skip = !empty($_GET['first']) ? $_GET['first'] : 0;

        $product_with_distinct_barcode = $this->get_product_with_distinct_barcode();

        $inventories_data = DB::table('inventory')
                            ->leftJoin('store_warehouse', 'store_warehouse.idstore_warehouse', '=', 'inventory.idstore_warehouse')
                            ->leftJoin('product_master', 'product_master.idproduct_master', '=', 'inventory.idproduct_master')
                            ->leftJoin('brands', 'product_master.idbrand', '=', 'brands.idbrand')
                            ->leftJoin('category', 'category.idcategory', '=', 'product_master.idcategory')
                            ->leftJoin('sub_category', 'sub_category.idsub_category', '=', 'product_master.idsub_category')
                            ->leftJoin('vendor_purchases_detail', 'vendor_purchases_detail.idproduct_master', '=', 'product_master.idproduct_master')
                            ->leftJoin('product_batch', 'product_batch.idproduct_master', '=', 'inventory.idproduct_master')
                            ->select(
                                'store_warehouse.idstore_warehouse', 
                                'product_master.idproduct_master', 
                                'product_master.name As product_name',
                                'product_master.barcode',
                                'vendor_purchases_detail.hsn',
                                'brands.name As brand_name',
                                'category.name As category_name',
                                'sub_category.name As sub_category_name',
                                'vendor_purchases_detail.expiry',
                                'inventory.quantity As total_quantity',
                                DB::raw('Round((CASE WHEN product_master.cgst IS NOT NULL AND product_master.sgst IS NOT NULL THEN (inventory.purchase_price + (inventory.purchase_price * (product_master.cgst + product_master.sgst))/100) ELSE inventory.purchase_price END), 2) AS purchase_price'),
                                'inventory.selling_price',
                                'inventory.mrp',
                                DB::raw('Round((CASE WHEN product_master.cgst IS NOT NULL AND product_master.sgst IS NOT NULL THEN (inventory.purchase_price + (inventory.purchase_price * (product_master.cgst + product_master.sgst))/100) ELSE inventory.purchase_price END) * inventory.quantity ,2) As purchase_cost'),
                                DB::raw('Round(inventory.selling_price * inventory.quantity, 2) As sales_cost')
                            )
                            ->whereIn('product_master.barcode', $product_with_distinct_barcode);
                   
        if(!empty($_GET['field']) && $_GET['field']=="brand"){
             $inventories_data->where('brands.name', 'like', $_GET['searchTerm'] . '%');
        }
         if(!empty($_GET['field']) && $_GET['field']=="category"){
             $inventories_data->where('category.name', 'like', $_GET['searchTerm'] . '%');
        }
         if(!empty($_GET['field']) && $_GET['field']=="sub_category"){
             $inventories_data->where('sub_category.name', 'like', $_GET['searchTerm'] . '%');
        }
         if(!empty($_GET['field']) && $_GET['field']=="barcode"){
             $barcode=$_GET['searchTerm'];
            $inventories_data->where('product_master.barcode', 'like', $barcode . '%');
        }
        
        
        if(!empty($_GET['idstore_warehouse'])) {
            $inventories_data->where('inventory.idstore_warehouse', $_GET['idstore_warehouse']);
        }       

        if(!empty($start_date) &&  !empty($end_date)) {
            $inventories_data->whereBetween('inventory.created_at',[$start_date, $end_date]);
        }

        $totalRecords = $inventories_data->count();
        $limit = abs($limit - $skip);
        $inventories = $inventories_data->skip($skip)->take($limit)->get();                          
        
        return response()->json(["statusCode" => 0, "message" => "Success", "data" => $inventories, 'total' => $totalRecords], 200);
    }

    public function get_inventory_state_data()
    {
        ini_set('max_execution_time', 14000);
        $product_with_distinct_barcode = $this->get_product_with_distinct_barcode();
        $total_product = DB::table('inventory')->leftJoin('product_master', 'product_master.idproduct_master', '=', 'inventory.idproduct_master')->select(DB::raw('DISTINCT(inventory.idproduct_master)'))->whereIn('product_master.barcode', $product_with_distinct_barcode)->count();
        $inventories_data = DB::table('inventory')
                            ->leftJoin('product_master', 'product_master.idproduct_master', '=', 'inventory.idproduct_master')
                            ->leftJoin('brands', 'product_master.idbrand', '=', 'brands.idbrand')
                            ->leftJoin('category', 'category.idcategory', '=', 'product_master.idcategory')
                            ->leftJoin('sub_category', 'sub_category.idsub_category', '=', 'product_master.idsub_category')
                            ->select(
                                'inventory.quantity As total_quantity',
                                DB::raw('Round((CASE WHEN product_master.cgst IS NOT NULL AND product_master.sgst IS NOT NULL THEN (inventory.purchase_price + (inventory.purchase_price * (product_master.cgst + product_master.sgst))/100) ELSE inventory.purchase_price END) * inventory.quantity ,2) As purchase_cost'),
                                DB::raw('Round(inventory.selling_price * inventory.quantity, 2) As sales_cost')
                            )
                            ->whereIn('product_master.barcode', $product_with_distinct_barcode);
        //
        if(!empty($_GET['idstore_warehouse'])) {
            $inventories_data->where('inventory.idstore_warehouse', $_GET['idstore_warehouse']);
        }                       
        
        if(!empty($_GET['field']) && $_GET['field']=="brand"){
            $inventories_data->where('brands.name', 'like', $_GET['searchTerm'] . '%');
        }
        if(!empty($_GET['field']) && $_GET['field']=="category"){
            $inventories_data->where('category.name', 'like', $_GET['searchTerm'] . '%');
        }
        if(!empty($_GET['field']) && $_GET['field']=="sub_category"){
            $inventories_data->where('sub_category.name', 'like', $_GET['searchTerm'] . '%');
        }

        $inventories = $inventories_data->get();
        $total_stock = 0;
        $toal_sales_cost = 0;
        $total_purchse_cost = 0;
        foreach($inventories as $inventory) {
            $total_stock = $total_stock + $inventory->total_quantity;
            $toal_sales_cost = $toal_sales_cost + $inventory->sales_cost;
            $total_purchse_cost = $total_purchse_cost + $inventory->purchase_cost;
        }
        $data = [
            'total_product' => $total_product,
            'total_stock' => $total_stock,
            'toal_sales_cost' => round($toal_sales_cost, 2),
            'total_purchse_cost' => round($total_purchse_cost, 2),
        ];
        
        return response()->json(["statusCode" => 0, "message" => "Success", "data" => $data, ], 200);
    }

    public function get_product_with_distinct_barcode()
    {
        $all_products =  DB::table('product_master')->select(DB::raw('DISTINCT(barcode)'))->where('barcode', '<>', '')->get()->toArray();
        $product_array = [];
        foreach($all_products as $key => $product){
            $product_array[$key] = $product->barcode;
        }
        
        return $product_array;
    }

    function removeDuplicates($array, $key)
    {
        $uniqueArray = [];
        $seenValues = [];
    
        foreach ($array as $item) {
            $value = $item[$key];
    
            if (!in_array($value, $seenValues)) {
                $uniqueArray[] = $item;
                $seenValues[] = $value;
            }
        }
    
        return $uniqueArray;
    }

    public function get_product_quantity($id)
    {
        $quantity = DB::table('product_batch')->select('quantity')->where('idproduct_master', $id)->first();
        return (array)$quantity;
    }

    public function get_product_name_and_barcode($id)
    {
        $data = DB::table('product_master')->select('name', 'barcode')->where('idproduct_master', $id)->first();
        return (array)$data;
    }

    public function get_vendor_detail($id)
    {
        $vendors = DB::table('vendor_purchases_detail')->select('quantity', 'expiry')->where('idproduct_master', $id)->first();
        return $vendors;
    }

    public function get_expire_report($id)
    {
        $expireAmount = DB::table('vendor_purchases_detail')->select('quantity', 'mrp', 'expiry')->where('idproduct_master', $id)->first();
        return $expireAmount;
    }

    public function get_product_data($id)
    {
        $product_data = DB::table('product_master')
                            ->leftJoin('category', 'category.idcategory', '=', 'product_master.idcategory')
                            ->leftJoin('sub_category', 'sub_category.idsub_category', '=', 'product_master.idsub_category')
                            ->leftJoin('sub_sub_category', 'sub_sub_category.idsub_sub_category', '=', 'product_master.idsub_sub_category')
                            ->leftJoin('brands', 'brands.idbrand', '=', 'product_master.idbrand')
                            ->where('product_master.idproduct_master', $id)
                            ->select(
                                'product_master.name',
                                'product_master.barcode',
                                'category.name As category_name',
                                'category.idcategory',
                                'sub_category.name As sub_category_name',
                                'sub_category.idsub_category',
                                'sub_sub_category.name AS sub_sub_category_name',
                                'sub_sub_category.idsub_sub_category',
                                'brands.name As brands_name',
                                'brands.idbrand'
                            )
                            ->first();
        return $product_data;
    }

    public function expried_and_expiring_inventory(Request $request)
    {
        $store_id = !empty($request->store_id) ? $request->store_id : null;
        $graph_type = !empty($request->graph_type) ? $request->graph_type : null;
        $start_date =  !empty($request->start_date) ? $request->start_date : null;
        $end_date = !empty($request->end_date)? $request->end_date :  null;
        $limit = !empty($request->rows) ? $request->rows : 50;
        $skip = !empty($request->first) ? $request->first : 0;
    
        $inventories_data = DB::table('product_master')
                            // ->leftJoin('brands', 'product_master.idbrand', '=', 'brands.idbrand')
                            // ->leftJoin('category', 'category.idcategory', '=', 'product_master.idcategory')
                            // ->leftJoin('sub_category', 'sub_category.idsub_category', '=', 'product_master.idsub_category')
                            ->leftJoin('inventory', 'inventory.idproduct_master', '=', 'product_master.idproduct_master');
        // ->whereIn('inventory.idproduct_master', $ids);
        
        if(!empty($start_date) &&  !empty($end_date)) {
            $inventories_data->whereBetween('inventory.created_at',[$start_date, $end_date]);
        }
    
        if($graph_type === 'brands') {
            $inventories_data->RightJoin('brands', 'brands.idbrand', '=', 'product_master.idbrand');
            $inventories_data->select('product_master.idbrand','product_master.idproduct_master', DB::raw('sum(inventory.quantity) as total_quantity'));
            $inventories_data->groupBy('product_master.idbrand','product_master.idproduct_master');
        }

        if($graph_type === 'category') {
            $inventories_data->leftJoin('category', 'category.idcategory', '=', 'product_master.idcategory');
            $inventories_data->select('product_master.idcategory','product_master.idproduct_master', DB::raw('sum(inventory.quantity) as total_quantity'));
            $inventories_data->groupBy('product_master.idcategory','product_master.idproduct_master');
        }

        if($graph_type === 'sub_category') {
            $inventories_data->leftJoin('sub_category', 'sub_category.idsub_category', '=', 'product_master.idsub_category');
            $inventories_data->select('product_master.idsub_category','product_master.idproduct_master', DB::raw('sum(inventory.quantity) as total_quantity'));
            $inventories_data->groupBy('product_master.idsub_category','product_master.idproduct_master');
        }

        if($graph_type === 'sub_sub_category') {
            $inventories_data->leftJoin('sub_sub_category', 'sub_sub_category.idsub_sub_category', '=', 'product_master.idsub_sub_category');
            $inventories_data->select('product_master.idsub_sub_category','product_master.idproduct_master', DB::raw('sum(inventory.quantity) as total_quantity'));
            $inventories_data->groupBy('product_master.idsub_sub_category','product_master.idproduct_master');
        }

        if(!empty($request->field) && $request->field =="brand"){
            $inventories_data->where('brands.name', 'like', $request->searchTerm . '%');
       }
        if(!empty($request->field) && $request->field=="category"){
            $inventories_data->where('category.name', 'like', $request->searchTerm . '%');
       }
        if(!empty($request->field) && $request->field=="sub_category"){
            $inventories_data->where('sub_category.name', 'like', $request->searchTerm . '%');
       }
        if(!empty($request->field) && $request->field=="barcode"){
            $barcode=$request->searchTerm;
           $inventories_data->where('product_master.barcode', 'like', $barcode . '%');
       }
       if(!empty($request->field) && $request->field=="product"){
           $inventories_data->where('product_master.name', 'like', $request->searchTerm . '%');
       }

       if(!empty($store_id)) {
           $inventories_data->where('inventory.idstore_warehouse', $store_id);
       }

        
        $totalRecords = $inventories_data->paginate(2)->total();
        $limit = abs($limit - $skip);
        $inventories = $inventories_data->skip($skip)->take($limit)->get();
        $total_expried_amount = 0;
        $total_xpiring_in_30_days_amount = 0;
        $total_not_expired_amount = 0;
        
        foreach($inventories as $inventory) {
            $expired_data = $this->get_expired_product($inventory->idproduct_master);
            $expiring_data = $this->get_expiring_in_30days($inventory->idproduct_master);
            $product_data = $this->get_product_data($inventory->idproduct_master);
            $not_expired = $this->get_not_expired_product($inventory->idproduct_master);
            if(!empty($product_data)) {
                $inventory->product_name = $product_data->name;
            }
            $inventory->expried = 0;
            $inventory->expiring_in_30_days = 0;
            $inventory->not_expired = 0;
            if(!empty($expired_data)) {
                $inventory->expried= $expired_data->quantity;
                $total_expried_amount += $expired_data->quantity * $expired_data->mrp;
            }
            if(!empty($expiring_data)) {
                $inventory->expiring_in_30_days = $expiring_data->quantity;
                $total_xpiring_in_30_days_amount += $expiring_data->quantity * $expiring_data->mrp;
            }
            if(!empty($not_expired)) {
                $inventory->not_expired = $not_expired->quantity;
                $total_not_expired_amount = $not_expired->quantity * $not_expired->mrp;
            }
        }

        $inventories = $this->data_formatting($inventories, $graph_type);
        $inventories['total_expried_amount'] = $total_expried_amount;
        $inventories['total_xpiring_in_30_days_amount'] = $total_xpiring_in_30_days_amount;
        $inventories['total_not_expired_amount'] = $total_not_expired_amount;

        return response()->json(["statusCode" => 0, "message" => "Success", "data" => $inventories, 'total' => $totalRecords], 200);
    }

    public function get_expired_product($id) {
        $expiredData = DB::table('vendor_purchases_detail')->select('quantity', 'mrp', 'expiry')->where('idproduct_master', $id)->where('expiry', '<', now()->toDateString())->first();
        return $expiredData;
    }

    public function get_not_expired_product($id) {
        $notExpiredData = DB::table('vendor_purchases_detail')->select('quantity', 'mrp', 'expiry')->where('idproduct_master', $id)->where('expiry', '>', now()->toDateString())->first();
        return $notExpiredData;
    }

    public function get_expiring_in_30days($id) {
        $expiredData = DB::table('vendor_purchases_detail')->select('quantity', 'mrp', 'expiry')->where('idproduct_master', $id)->where('expiry', '>', now()->toDateString())->where('expiry', '<', now()->addDays(30))->first();
        return $expiredData;
    }

    public function get_product_ids()
    {
        $expiredProducts = DB::table('vendor_purchases_detail')
            ->select('idproduct_master')
            ->where('expiry', '<', now()->toDateString())
            ->where('expiry', '<>', '')
            ->get();
        $expiringProducts = DB::table('vendor_purchases_detail')
            ->select('idproduct_master')
            ->where('expiry', '>', now()->toDateString())
            ->where('expiry', '<', now()->addDays(30))
            ->get();    
        
        foreach($expiredProducts as $expiredProduct) {
            $ids[] = $expiredProduct->idproduct_master;
        }

        foreach($expiringProducts as $expiringProduct) {
            $ids[] = $expiringProduct->idproduct_master;
        }

        return $ids;
    }

    public function data_formatting($data, $graph_type="")
    {
        $transformedData = [];

        foreach ($data as $item) {        
            if($graph_type === 'brands') {
                $idbrand = $item->idbrand;
                $brand_name = $this->get_name($idbrand, 'brands');  
                $key = "{$idbrand}";
                if (!isset($transformedData[$key])) {
                    $transformedData[$key] = [
                        'idbrand' => $idbrand,  
                        'brand_name'=> $brand_name,                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 
                        'totals' => [],
                    ];
                }
            } else if($graph_type === 'category') {
                $idcategory = $item->idcategory;
                $category_name = $this->get_name($idcategory, 'category');
                $key = "{$idcategory}";
                if (!isset($transformedData[$key])) {
                    $transformedData[$key] = [
                        'idcategory' => $idcategory,
                        'category_name' => $category_name,
                        'totals' => [],
                    ];
                }
            } else if($graph_type === 'sub_category') {
                $idsub_category = $item->idsub_category;
                $sub_category_name = $this->get_name($idsub_category, 'sub_category');
                $key = "{$idsub_category}";
                if (!isset($transformedData[$key])) {
                    $transformedData[$key] = [
                        'idsub_category' => $idsub_category,
                        'sub_category_name' => $sub_category_name,
                        'totals' => [],
                    ];
                }
            } else if($graph_type === 'sub_sub_category') {
                $idsub_sub_category = $item->idsub_sub_category;
                $sub_sub_category_name = $this->get_name($idsub_sub_category, 'sub_sub_category');
                $key = "{$idsub_sub_category}";
                if (!isset($transformedData[$key])) {
                    $transformedData[$key] = [
                        'idsub_sub_category' => $idsub_sub_category,
                        'sub_sub_category_name' => $sub_sub_category_name,
                        'totals' => [],
                    ];
                }
            } else {
                $idproduct_master = !empty($item->idproduct_master) ? $item->idproduct_master : '';
                $key = "{$idproduct_master}";
                if (!isset($transformedData[$key])) {
                    $transformedData[$key] = [
                        'idproduct_master' => !empty($item->idproduct_master) ? $item->idproduct_master : '',
                        'product_name' => !empty($item->product_name) ? $item->product_name : '',
                        'expired' => $item->expried,
                        'expiring_in_30days_amount' => $item->expiring_in_30_days,
                        'not_expired' => $item->not_expired,
                    ];
                }
            }
            
            if($graph_type === 'sub_category' || $graph_type === 'category' || $graph_type === 'sub_sub_category' || $graph_type === 'brands') {
                $transformedData[$key]['totals'][] = [
                    'idproduct_master' => !empty($item->idproduct_master) ? $item->idproduct_master : '',
                    'product_name' => !empty($item->product_name) ? $item->product_name : '',
                    'expired' => $item->expried,
                    'expiring_in_30days_amount' => $item->expiring_in_30_days,
                    'not_expired' => $item->not_expired,
                ];
            }
        }

        $transformedData = array_values($transformedData);

        return $transformedData;
    }

    public function get_name($id, $table_name)
    {
        $name = '';
        if($table_name === "brands") {
            $column = 'brand';
        } else {
            $column = $table_name;
        }
        if(!empty($table_name)) {
            $name = DB::table($table_name)
                    ->select('name')
                    ->where('id' . $column, $id)
                    ->first();
        }
        return $name->name??"";
    }
}

