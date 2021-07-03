<?php

namespace smasif\ShurjopayLaravelPackage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class ShurjopayController extends Controller
{
    public function response(Request $request)
    {

        $order_id = $request->get("order_id");
     

        $server_url = config('shurjopay.server_url');
        $response_encrypted = $request->spdata;
        // $response_decrypted = file_get_contents($server_url . "/merchant/decrypt.php?data=" . $response_encrypted);
        // dd($response_encrypted);
        $response_decrypted = $this->decrypt($response_encrypted);
        // dd($response_decrypted);
        // exit;

        $data = simplexml_load_string($response_decrypted) or die("Error: Cannot create object");
        // dd($data);
        // exit;
        $tx_id = $data->txID;
        $bank_tx_id = $data->bankTxID;
        $amount = $data->txnAmount;
        $bank_status = $data->bankTxStatus;
        $sp_code = $data->spCode;
        $sp_code_des = $data->spCodeDes;
        $sp_payment_option = $data->paymentOption;
        $full_response = (string) json_encode($data);
       
        $status = "";
        switch ($sp_code) {
            case '000':
                $res = array('status' => true, 'msg' => 'Action Successful');
                $status = "Success";
                break;
            case '001':
                $status = "Failed";
                $res = array('status' => false, 'msg' => 'Action Failed');
                break;
        }

        $success_url = $request->get('success_url');

        if ($success_url) {
            header("location:" . $success_url . "?status={$status}&msg={$res['msg']}&tx_id={$tx_id}&bank_tx_id={$bank_tx_id}"
                . "&amount={$amount}&bank_status={$bank_status}&sp_code={$sp_code}" .
                "&sp_code_des={$sp_code_des}&sp_payment_option={$sp_payment_option}");
        }
        if ($res['status']) {
            $orderUpdateSql = "update orders set payment_status = 'paid', payment_details = '$full_response' where id=$order_id";
            DB::statement($orderUpdateSql); 

            $orderDetailsUpdateSql = "update order_details set payment_status = 'paid', delivery_status='processing' where order_id = $order_id";
            DB::statement($orderDetailsUpdateSql); 

            header("Location: checkout/order-confirmed?payment_status=1");
            //echo "Success";
            die();
        } else {
            $orderUpdateSql = "update orders set payment_status = 'unpaid', payment_details = '$full_response' where id=$order_id";
            DB::statement($orderUpdateSql); 
            // no need to update order_details table

            header("Location: checkout/order-confirmed?payment_status=0");
            echo "Fail";
            die();
        }
    }

        private function decrypt($encryptedText = "")
        {

            $server_url = config('shurjopay.server_url');
            // $response_decrypted = file_get_contents($server_url . "/merchant/decrypt.php?data=" . $response_encrypted);
            $decrypt_url = $server_url . "/merchant/decrypt.php";
            if (empty($encryptedText)) return null;
            $url = $decrypt_url . '?data=' . $encryptedText;
            // dd($url);
            $ch = curl_init();  
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);    
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $response_decrypted = curl_exec($ch);
            curl_close ($ch);
            // var_dump($response_decrypted);exit;
            return $response_decrypted;            
        }
}
