<?php
if ( ! defined( 'ABSPATH' ) ) {
	/** Set up WordPress environment */
	require_once __DIR__ . '/wp-load.php';
}

$MonaURL 	 = 'https://phc.taoquan.vn/AndroidService.svc/GetStock';
$MonaURL_2 	 = 'https://phc.taoquan.vn/AndroidService.svc/GetPrice';
$MonaURL_3 	 = 'https://phc.taoquan.vn/AndroidService.svc/GetGiftToday';

date_default_timezone_set('Asia/Bangkok');
$MonaURLTime = date('Y-m-d H:i'); 

$MonaURLDateTime = new DateTime($MonaURLTime, new DateTimeZone('GMT'));
$MonaURLDateTime->modify('-1 hour');
$MonaURLDateTimeFormatted = $MonaURLDateTime->format('Y-m-d H:i');

if( !empty( $MonaURLDateTimeFormatted ) ){
	// $MonaURLDateTimeFormatted = '2024-05-08 16:00';
	$MonaURL .= '?fromdate=' . urlencode( $MonaURLDateTimeFormatted );
	$MonaURL_2 .= '?fromdate=' . urlencode( $MonaURLDateTimeFormatted );
}

$curl = curl_init();
curl_setopt_array($curl, array(
  CURLOPT_URL => $MonaURL,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
));

$response = curl_exec($curl);
curl_close($curl);

$responseFormatted 	= 	json_decode( $response );
$responseData 		= 	$responseFormatted->Data;

$arrayProduct = [];
if( is_array( $responseData ) && !empty( $responseData ) ){
	foreach ( $responseData as $key => $objectData ) {
		$ProductCode 	= $objectData->ProductCode;
		$ProductId 		= wc_get_product_id_by_sku( $ProductCode );
		$ProductObject  = wc_get_product( $ProductId );

		if( $ProductObject ){
			$ProductQuantity = $objectData->Quantity;
			$ProductPriceMax = $objectData->PriceMax;
			$ProductPrice 	 = $objectData->Price;

			update_post_meta( $ProductId, '_manage_stock', 'yes' ); // yes or no
			update_post_meta( $ProductId, '_stock', $ProductQuantity );
			update_post_meta( $ProductId, '_stock_status', ( $ProductQuantity > 0 ? 'instock' : 'outofstock' ) );
			$arrayProduct[] = $ProductCode . ' | ' . $objectData->ProductName . ' - Tồn kho = ' . $ProductQuantity . ' [PID=' . $ProductObject->get_id() . ']';

			$ProductObject->save();
		}
	}
}

// ***************************

$curl2 = curl_init();
curl_setopt_array($curl2, array(
  CURLOPT_URL => $MonaURL_2,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
));

$response2 = curl_exec($curl2);
curl_close($curl2);

$responseFormatted2 	= 	json_decode( $response2 );
$responseData2 		= 	$responseFormatted2->Data;

if( is_array( $responseData2 ) && !empty( $responseData2 ) ){
	foreach ( $responseData2 as $key => $objectData ) {
		$ProductCode 	= $objectData->ProductCode;

		$ProductId 		= wc_get_product_id_by_sku( $ProductCode );
		$ProductObject  = wc_get_product( $ProductId );
		if( $ProductObject ){
			$ProductPriceMax = $objectData->PriceMax;
			$ProductPrice 	 = $objectData->Price;

			if( $ProductPriceMax > 0 && $ProductPrice > 0 ){
				if( $ProductPrice < $ProductPriceMax ){
					// Update the product price
					update_post_meta($ProductId, '_regular_price', $ProductPriceMax);
					update_post_meta($ProductId, '_sale_price', $ProductPrice);
					update_post_meta($ProductId, '_price', $ProductPrice);
					wc_delete_product_transients( $ProductId );
					$arrayProduct[] = $ProductCode . ' | ' . $objectData->ProductName . ' - Giá bán < Giá niêm yết [PID=' . $ProductObject->get_id() . ']';
				}else{
					// Update the product price
					update_post_meta($ProductId, '_regular_price', $ProductPriceMax);
					update_post_meta($ProductId, '_sale_price', false );
					update_post_meta($ProductId, '_price', $ProductPriceMax);
					wc_delete_product_transients( $ProductId );
					$arrayProduct[] = $ProductCode . ' | ' . $objectData->ProductName . ' - Giá bán < Giá niêm yết';
				}
			}else if( $ProductPrice > 0 ){
				update_post_meta($ProductId, '_regular_price', $ProductPrice);
				update_post_meta($ProductId, '_sale_price', false );
				update_post_meta($ProductId, '_price', $ProductPrice);
				wc_delete_product_transients( $ProductId );
				$arrayProduct[] = $ProductCode . ' | ' . $objectData->ProductName . ' - Giá bán > 0 [PID=' . $ProductObject->get_id() . ']';
			}else{
				update_post_meta($ProductId, '_regular_price', false);
				update_post_meta($ProductId, '_sale_price', false );
				update_post_meta($ProductId, '_price', false);
				wc_delete_product_transients( $ProductId );
				$arrayProduct[] = $ProductCode . ' | ' . $objectData->ProductName . ' - Cả 2 giá = 0 [PID=' . $ProductObject->get_id() . ']';
			}

			$ProductObject->save();
			if( !empty( $ProductObject->get_parent_id() ) ){
				$ProductObjectParent = wc_get_product( $ProductObject->get_parent_id() );
				$ProductObjectParent->save();
			}
		}
	}
}

// ***************************
// $curl3 = curl_init();
// curl_setopt_array($curl3, array(
//   CURLOPT_URL => $MonaURL_3,
//   CURLOPT_RETURNTRANSFER => true,
//   CURLOPT_ENCODING => '',
//   CURLOPT_MAXREDIRS => 10,
//   CURLOPT_TIMEOUT => 0,
//   CURLOPT_FOLLOWLOCATION => true,
//   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//   CURLOPT_CUSTOMREQUEST => 'GET',
// ));

// $response = curl_exec($curl3);
// curl_close($curl);

// $responseFormatted 	= 	json_decode( $response );

// $product_ids_api_promotion = [];
// $idsCheck = [];

// if ( !empty($responseFormatted) && !empty($responseFormatted->Promotions) ) {
// 	foreach ($responseFormatted->Promotions as $dataGift) {

// 		$ProductCode 	= $dataGift->ProductCode;
// 		$variation_id 	= wc_get_product_id_by_sku( $ProductCode );

// 		$variation = wc_get_product( $variation_id );

// 		// Kiểm tra xem đối tượng có phải là phiên bản sản phẩm không
// 		if ( $variation && $variation->is_type( 'variation' ) ) {

// 			$promotion_information = [];
			
// 			if ( !empty($dataGift->Items) ) {
// 				foreach ($dataGift->Items as $dataItems) {
// 					array_push($promotion_information, ['content' => $dataItems->WebDesc, 'Link' => $dataItems->WebUrl]);	
// 				}
				
// 				$idProduct = $variation->get_parent_id();
// 				if( !empty( $idProduct ) ){
// 					if (!in_array($idProduct, $idsCheck)) {
// 						$idsCheck[] = $idProduct;
// 						update_field('mona_product_promotion_information', $promotion_information, $idProduct);
// 						$product_ids_api_promotion[] =  $idProduct;
// 					}
					
// 				}
// 			}
// 		}	
// 	}
// }

// if (!empty(get_option('product_ids_api_promotion', [])) || !empty($product_ids_api_promotion)) {
// 	foreach (get_option('product_ids_api_promotion', []) as $dataidCheck) {
// 		if ( !in_array($dataidCheck, $idsCheck)) {
// 			update_field('mona_product_promotion_information', [], $dataidCheck);
// 		}
		
// 		if (empty($idsCheck)) {
// 			update_field('mona_product_promotion_information', [], $dataidCheck);
// 		}
// 	}
	
// 	update_option('product_ids_api_promotion', $product_ids_api_promotion);
// }

$headers = array('Content-Type: text/html; charset=UTF-8');
$to = 'nguyenleeminhhieu.work@gmail.com';
$subject = sprintf( __('Táo Quân x MONA x %s: Cập nhật', 'mona-admin') , get_bloginfo('name') );
$message = '<p>MONA.Media x Táo Quân x HPP: cập nhật từ mốc thời gian ' . $MonaURLDateTimeFormatted . '</p>';
$message .= implode( '<br>' , $arrayProduct );
// wp_mail( $to , $subject , $message, $headers );