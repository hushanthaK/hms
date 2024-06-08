<?php 
use App\Permission;
use App\Setting;
use App\Reservation;
use App\Order;
use App\OrderHistory;
use App\Room;
use App\Unit,App\RoomType,App\Customer;
use App\PaymentHistory;
use App\BookedRoom;
use App\DynamicDropdown;
use App\Role;
use App\Amenities;
use App\ExpenseCategory;
function lang_trans($key){
    $defaultLang = 'en';
    if(isset(Session::get('settings')['site_language'])){
        $defaultLang = Session::get('settings')['site_language'];
    }
    if(isset(config('lang_admin')[$key][$defaultLang])){
        return config('lang_admin')[$key][$defaultLang];
    }
    return $key;
}
function getAuthUserInfo($info = 'all'){
    $user = Auth::user() ? Auth::user() : null;
    
    if($info == 'id'){
      return $user->id;  
    }
    
    $user->additional_info = null;
    if($user){
        $customerInfo = Customer::where('user_id', $user->id)->first();
        if($customerInfo) {
            $user->additional_info = $customerInfo;
        }
    }
    return $user;
}
function getCustomerInfo($customerId){
    return Customer::where('id', $customerId)->first();
}
function setSettings(){
    $settings = Setting::pluck('value','name');
    Session::put('settings', $settings);
    return $settings;
}
function getSettings($clm=null){
    // if(Session::get('settings')){
    //     $settings = Session::get('settings');
    // } else {
        $settings = setSettings();
    //}

    if($clm==null){
        return $settings;
    }
    if(isset($settings[$clm])){
        return $settings[$clm];
    }
    return '';
}
function getDynamicDropdownById($id, $clm = 'all'){
    $data = DynamicDropdown::whereId($id)->first();
    if($data){
        if($clm != 'all'){
            return $data->{$clm};
        }
    }
    return $data;
}
function getDynamicDropdownRecord($where){
    return DynamicDropdown::where($where)->first();
}
function getDynamicDropdownList($dropdownName){
    $data = DynamicDropdown::where('dropdown_name', $dropdownName)->where('is_deleted', 0)->where('status', 1)->get();
    $list = [];
    if($data){
        foreach ($data as $key => $value) {
            $list[$value->id] = $value->dropdown_value;
        }
    }
    return $list;
}
function getUnits(){
    return getDynamicDropdownList('measurement');
}
function getRoomByNum($roomNum){
    return Room::where('room_no', $roomNum)->first();
}
function getRoomById($roomId){
    return Room::where('id', $roomId)->first();
}
function getRoomTypeById($id){
    return RoomType::where('id', $id)->first();
}
function getRoomTypesList($listType = 'original'){
    if($listType == 'custom'){
        return RoomType::select('id',DB::raw('CONCAT(title, " (Price: ", base_price,")") AS title'))->whereStatus(1)->whereIsDeleted(0)->orderBy('order_num','ASC')->pluck('title','id');
    }
    if($listType == 'original'){
        return RoomType::whereStatus(1)->whereIsDeleted(0)->orderBy('order_num','ASC')->pluck('title','id');
    }
}
function getRoomTypesListWithRooms(){
    return RoomType::with('rooms')->whereStatus(1)->whereIsDeleted(0)->orderBy('order_num','ASC')->get();
}
function getReservationById($id){
    return Reservation::whereId($id)->first();
}
function getAmenitiesById($id){
    return Amenities::where('id', $id)->first();
}
function getCustomerByUserId($userId){
    return Customer::whereUserId($userId)->first();
}
function getCustomerList($type='pluck'){
    if($type == 'get') return Customer::select('id',DB::raw('CONCAT(name, " (", mobile,")") AS display_text'))->whereNotNull('name')->whereIsDeleted(0)->orderBy('name','ASC')->get();
    else return Customer::select('id',DB::raw('CONCAT(name, " (", mobile,")") AS display_text'))->whereIsDeleted(0)->orderBy('name','ASC')->pluck('display_text','id');
}
function getExpenseCategoryList(){
    return ExpenseCategory::whereStatus(1)->orderBy('name','ASC')->pluck('name','id');
}
function getBookedRooms($params = []){
    $bookedRooms = [];
    $query = Reservation::with('booked_rooms')->whereStatus(1)->whereIsDeleted(0)->whereIsCheckout(0)->orderBy('created_at','DESC');
    if(isset($params['reservation_id'])){
        $query->where('id', $params['reservation_id']);
    }
    $reservationData = $query->get();

    $isBooked = true;
    $dateRange = null;
    if(isset($params['checkin_date']) && isset($params['checkout_date'])){
        $dateRange = dateRange(($params['checkin_date']), ($params['checkout_date']),'+1 day','Y-m-d H:i:s');
    }
    if($reservationData->count()>0){
        foreach($reservationData as $val){           
            if($val->booked_rooms){
                foreach($val->booked_rooms as $k=>$v){
                    if($dateRange){
                        $isBooked = false;
                        if(in_array(dateConvert($v->check_in), $dateRange)){
                            $isBooked = true;
                        }
                        if(in_array(dateConvert($v->check_out), $dateRange)){
                            $isBooked = true;
                        } 
                        if(isset($params['checkin_date'])){
                            if(new DateTime($v->check_out) > new DateTime($params['checkin_date'])){
                                $isBooked = true;
                            } else {
                                $isBooked = false; 
                            } 
                        }                     
                    }
                    
                    if($v->is_checkout == 0 && $isBooked){
                        $bookedRooms[$v->room_id] = $v->room_id;
                    }
                }
            }
        }
    }
    return $bookedRooms;
}

function getAllBookedRooms(){
    $bookedRooms = [];
    $reservationData = Reservation::with('booked_rooms')->whereStatus(1)->whereIsDeleted(0)->whereIsCheckout(0)->orderBy('created_at','DESC')->get();
    if($reservationData && $reservationData->count()>0){
        foreach($reservationData as $val){
            if($val->booked_rooms){
                foreach($val->booked_rooms as $k=>$v){

                    $date_1 = new DateTime(date('Y-m-d'));
                    $date_2 = new DateTime($v->check_out);
                    $isValidDate = ($date_1 < $date_2) ? true : false;

                    if($v->is_checkout == 0 && $isValidDate){
                        $bookedRooms[$v->room_id] = $v->room_id;
                    }
                }
            }
        }
    }
    return $bookedRooms;
}

function getCalendarEventsByDate($params){
    $datalist = [];
    $bookedRooms = [];
    $bookedDates = [];
    $paramsDatesRange = $dateRange = dateRange(dateConvert($params['start_date']), dateConvert($params['end_date']));

    $bookedRoomsData = BookedRoom::whereIsCheckout(0)->orderBy('check_in','DESC')->get();
    
    if($bookedRoomsData && $bookedRoomsData->count()>0){
        foreach($bookedRoomsData as $k=>$v){
            $dateRange = dateRange(dateConvert($v->check_in), dateConvert($v->check_out));
            foreach ($dateRange as $key => $date) {
                $bookedRooms[$v->room_id][dateConvert($date, 'Ymd')] = $v->room_id;
            }
            $bookedDates[] = ['roomId'=>$v->room_id, 'dateRange'=>$dateRange];
            $datalist[] = [
                'title'=>$v->room->room_no.' ('.$v->room_type->title.')',
                'start'=>dateConvert($v->check_in).'T01:00:00+05:30',
                'end'=>dateConvert($v->check_out).'T01:00:00+05:30',
                'color'=>'#f56868',
                'url'=>route('check-out-room',[$v->reservation_id]),
                'extendedProps'=>['is_booked'=>1, 'room_info'=>$v->room],
            ];

        }
    }

    $allRooms = Room::whereIsDeleted(0)->whereStatus(1)->get();
    foreach ($allRooms as $key => $room) {
        $dates = (isset($bookedDates[$room->id])) ? $bookedDates[$room->id] : [];
        foreach ($paramsDatesRange as $d) {
            //if(count($bookedRooms) > 0){
                if(!isset($bookedRooms[$room->id][dateConvert($d, 'Ymd')]) ){
                    $datalist[] = [
                        'title'=>$room->room_no.' ('.$room->room_type->title.')',
                        'start'=>$d.'T01:00:00+05:30',
                        'end'=>$d.'T01:00:00+05:30',
                        'color'=>'#02b92e',
                        'url'=>route('quick-check-in'),
                        'extendedProps'=>['is_booked'=>0, 'room_info'=>$room]
                    ];
                }
            //}
        }
    }
    //dd($params,$datalist, $allRooms);
    return $datalist;
}

function getCurrencyList(){
    $list = config('currencies')['CURRENCY_LIST'];
    $currencies = [];
    foreach($list as $val){
        $currencies[$val['code']] = $val['code'].' ('.$val['country'].')';
    }
    return $currencies;
}
function getCurrencySymbol($isCode=false){
    $settings = getSettings();
    if(isset($settings['currency_symbol']) && $settings['currency_symbol']!='' && !$isCode){
        return $settings['currency_symbol'];
    }
    if(isset($settings['currency']) && $settings['currency']!=''){
        return $settings['currency'];
    }
    return ($isCode) ? 'USD' : '$';
}
function getCountryList(){
    $list = config('constants.COUNTRY_LIST');
    foreach($list as $k=>$val){
        $countries[$val['name']] = $val['name'];
    }
    return $countries;
}
function getRoles(){
    return Role::pluck('slug','id')->toArray();
}
function getMenuPermission(){
    $permissions = Permission::where('permission_type','menu')->get();
    $roles = getRoles();
    $permissionArr = [];
    if($permissions){
        foreach($permissions as $k=>$val){
            $permissionArr[$val->slug] = $val->{$roles[Auth::user()->role_id]};
        }
    }
    return $permissionArr;
}
function getRoutePermission(){
    $permissions = Permission::where('permission_type','route')->get();
    $roles = getRoles();
    $permissionArr = [];
    if($permissions){
        foreach($permissions as $k=>$val){
           $permissionArr[$val->slug] = $val->{$roles[Auth::user()->role_id]};
        }
    }
    return $permissionArr;
}

function genRandomValue($length=5,$type='digit',$prefix=null){
    if($type=='digit'){
        $characters = date('Ymd').'123456789987654321564738291918273645953435764423'.time();
    } else {
        $characters = date('Ymd').'192837465TransactionRandomId987654321AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz111xxxBheemSwamixxx9OO14568O8xxxBikanerRajasthan34OO1'.time();
    }
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $prefix.$randomString;
}

function getNextPrevDate($isDate='prev', $days=null){
    if($isDate=='prev'){
        $symbol = '-';
    } else {
        $symbol = '+';
    }
    if($days==null){
        $days = getSettings('default_rec_days');
    }
    return date('Y-m-d', strtotime(date('Y-m-d'). $symbol.$days.' days'));
}

function addSubDate($isDate, $val, $date, $format='d-m-Y', $adsSub='days'){
    //$isDate: +,- | $val: numericVal | $adsSub: days, months, year
    return date($format, strtotime($date. $isDate.$val.' '.$adsSub));
}

function dateConvert($date=null,$format=null){
    if($date==null)
        return date($format);
    if($format==null)
        return date('Y-m-d',strtotime($date));
    else 
        return date($format,strtotime($date));
}

function dateDiff($sDate, $eDate, $format = 'days'){
    $date1=date_create($sDate);
    $date2=date_create($eDate);
    $diff=date_diff($date1,$date2);
    if($format == 'days') {
        return $diff->format("%a");
    }
    return $diff->format("%R%a"); // if daysWIthSymbol
}

function dateRange($first, $last, $step = '+1 day', $output_format = 'Y-m-d' ) {

    $dates = array();
    $current = strtotime($first);
    $last = strtotime($last);

    while( $current <= $last ) {

        $dates[] = date($output_format, $current);
        $current = strtotime($step, $current);
    }

    return $dates;
}

function timeConvert($time,$format=null){
    if($format==null)
        return date('H:i:s',strtotime($time));
    else 
        return date($format,strtotime($time));
}
function timeFormatAmPm($time=null){
    if($time==null || $time==''){
        return '';
    }
    $exp = explode(' ', $time);
    $temp = date_parse($exp[0]);
    $temp['minute'] = str_pad($temp['minute'], 2, '0', STR_PAD_LEFT);
    return date('h:i a', strtotime($temp['hour'] . ':' . $temp['minute']));
}

function limit_text($text, $limit) {
  if (strlen($text) > $limit) {
        $text = substr($text, 0, $limit) . '...';
  }
  return $text;
}
function limit_words($string, $word_limit)
{
    if (str_word_count($string, 0) > $word_limit) {
        $words = explode(" ",$string);
        return implode(" ",array_splice($words,0,$word_limit)).'...';
    }
    return $string;
}

function checkFile($filename,$path,$default=null) {
    $src=url('public/images/'.$default);
    $path='public/'.$path;
    if($filename != NULL && $filename !='' && $filename != '0')
    {
        $file_path = app()->basePath($path.$filename);
        if(File::exists($file_path)){
            $src=url($path.$filename);
        } 
    }
    return $src;      
}
function unlinkImg($img,$path) {
    if($img !=null || $img !='')
    {
        $path='public/'.$path;
        $image_path = app()->basePath($path.$img);
        if(File::exists($image_path)) 
            unlink($image_path);
    }       
}

function getNextInvoiceNo($type=null){
    if($type=='ph'){
        //$data = PaymentHistory::whereNotNull('transaction_id')->orderBy('transaction_id','DESC')->first();
        $data = genRandomValue(8, 'mix');
        return $data;
    } else if($type=='orders'){
        $data = Order::whereNotNull('invoice_num')->orderBy('invoice_num','DESC')->first();
    } else {
        $data = Reservation::whereNotNull('invoice_num')->orderBy('invoice_num','DESC')->first();
    }
    
    if($data){
        $nextNum = ($type=='ph') ? $data->transaction_id+1 : $data->invoice_num+1;
    } else {
        $nextNum ='1';
    }
    return $nextNum;
}

function getStatusBtn($status){
    $txt = '';
    if(isset(config('constants.LIST_STATUS')[$status])){
        $txt = config('constants.LIST_STATUS')[$status];
    }
    if($status==1){
        return '<button type="button" class="btn btn-success btn-xs">'.$txt.'</button>';
    } else {
        return '<button type="button" class="btn btn-default btn-xs">'.$txt.'</button>';
    }
}
function getTableNums($excOrderId=0){
    $bookedTablesQuery =  OrderHistory::where('is_book',1);
    if($excOrderId>0){
        $bookedTablesQuery->where('order_id','<>',$excOrderId);
    }
    $bookedTables =  $bookedTablesQuery->pluck('table_num')->toArray();
    $tableNums = [];
    for($i=1; $i<=50; $i++){
        if(!in_array($i,$bookedTables)) $tableNums[$i] = $i;
    }
    return $tableNums;
}

function isTableBook($tableNum=0){
    return OrderHistory::where('table_num',$tableNum)->where('is_book',1)->orderBy('id','DESC')->first();
}
function getOrderInfo($id){
    return Order::where('reservation_id',$id)->first();
}

function gstCalc($amount,$type,$gstPerc=null,$cgstPerc=null){
    $gstAmount = $cgstAmount = 0;
    if($type=='room_amount'){
        $gstAmount = ($gstPerc/100)*$amount;
        $cgstAmount = ($cgstPerc/100)*$amount;
    } else {
        $gstAmount = ($gstPerc/100)*$amount;
        $cgstAmount = ($cgstPerc/100)*$amount;
    }
    
    return ['gst'=>$gstAmount, 'cgst'=>$cgstAmount];
} 

function calcFinalAmount($val, $isTotalWithGst = 0){
    $settings = getSettings();
    $totalAmount = 0;   
    if($val->booked_rooms){
        foreach($val->booked_rooms as $key=>$roomInfo){
            $durOfStay = dateDiff(dateConvert($roomInfo->check_in), dateConvert($roomInfo->check_out), 'days');
            $durOfStay = ($durOfStay == 0) ? 1 : $durOfStay;
            $perRoomPrice = ($durOfStay * $roomInfo->room_price);
            $totalAmount = $totalAmount+$perRoomPrice;
        }
    }

    $gstPerc = $val->gst_perc;
    $cgstPerc = $val->cgst_perc;      
    if($val->is_checkout == 0 && $isTotalWithGst == 1){
        $gstPerc = $settings['gst'];
        $cgstPerc = $settings['cgst'];
    }

    $gstCal = gstCalc($totalAmount,'room_amount', $gstPerc, $cgstPerc);
    
    $totalRoomAmountGst = $gstCal['gst'];
    $totalRoomAmountCGst = $gstCal['cgst'];
    $totalRoomAmountDiscount = ($val->discount > 0 ) ? $val->discount : 0;
    $advancePayment = ($val->advance_payment > 0 ) ? $val->advance_payment : 0;
    $additionalAmount = ($val->addtional_amount > 0 ) ? $val->addtional_amount : 0;

    $finalRoomAmount = $totalAmount+$totalRoomAmountGst+$totalRoomAmountCGst-$advancePayment-$totalRoomAmountDiscount;


    //start calculation of order amount
    $totalOrderAmountGst = $totalOrderAmountCGst = $totalOrderAmountDiscount = $orderGstPerc = $orderCGstPerc = 0;
    $gstFoodApply = 1;

    $orderInfo = getOrderInfo($val->id);
    if($orderInfo){
        $orderGstPerc = $orderInfo->gst_perc;
        $orderCGstPerc = $orderInfo->cgst_perc;

        $totalOrderAmountDiscount = $orderInfo->discount;
        $gstFoodApply = ($orderInfo->gst_apply==1) ? 1 : 0;
    }
    

    $totalOrdersAmount = 0;
    if($val->orders_items->count()>0){
        foreach($val->orders_items as $k=>$orderVal){
            $totalOrdersAmount = $totalOrdersAmount + ($orderVal->item_qty*$orderVal->item_price);
        }
    }

    if($isTotalWithGst == 1){
        $orderGstPerc = $settings['food_gst'];
        $orderCGstPerc = $settings['food_cgst'];
    }
    $gst = gstCalc($totalOrdersAmount,'food_amount',$orderGstPerc,$orderCGstPerc);
    $totalOrderAmountGst = $gst['gst'];
    $totalOrderAmountCGst = $gst['cgst'];

    $finalOrderAmount = ($totalOrdersAmount+$totalOrderAmountGst+$totalOrderAmountCGst-$totalOrderAmountDiscount);

    return [
        'totalRoomGstPerc' => checkAmount($gstPerc),
        'totalRoomCGstPerc' => checkAmount($cgstPerc),
        'totalRoomAmountGst' => checkAmount($totalRoomAmountGst),
        'totalRoomAmountCGst' => checkAmount($totalRoomAmountCGst),
        'totalRoomAmountDiscount'=> checkAmount($totalRoomAmountDiscount),
        'subtotalRoomAmount'=> checkAmount($totalAmount),
        'finalRoomAmount'=> checkAmount($finalRoomAmount),

        'totalOrderGstPerc' => checkAmount($orderGstPerc),
        'totalOrderCGstPerc' => checkAmount($orderCGstPerc),
        'totalOrderAmountGst'=> checkAmount($totalOrderAmountGst),
        'totalOrderAmountCGst'=> checkAmount($totalOrderAmountCGst),
        'totalOrderAmountDiscount'=> checkAmount($totalOrderAmountDiscount),
        'subtotalOrderAmount'=> checkAmount($totalOrdersAmount),
        'finalOrderAmount'=> checkAmount($finalOrderAmount),
        'gstFoodApply'=> checkAmount($gstFoodApply),

        'advancePayment'=> checkAmount($advancePayment),
        'additionalAmount'=> checkAmount($additionalAmount),
    ];
}

function checkAmount($val){
    return ($val > 0) ? $val : 0;
}

function getMaxDiscount($amount,$perc=100){
    //$maxDiscount = ($perc/100)*$amount;
    $maxDiscount = $amount;
    return $maxDiscount;
}  
function numberFormat($num){
        return sprintf('%0.2f',$num);
}
function getIndianCurrency(float $number){
    $negative = false;
    if($number < 0){
        $negative = true;
        $number = abs($number);
    }
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(0 => '', 1 => 'one', 2 => 'two',
        3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
        7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve',
        13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
        16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
        19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
        40 => 'forty', 50 => 'fifty', 60 => 'sixty',
        70 => 'seventy', 80 => 'eighty', 90 => 'ninety');
    $digits = array('', 'hundred','thousand','lakh', 'crore');
    while( $i < $digits_length ) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += $divider == 10 ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number].' '. $digits[$counter]. $plural.' '.$hundred:$words[floor($number / 10) * 10].' '.$words[$number % 10]. ' '.$digits[$counter].$plural.' '.$hundred;
        } else $str[] = null;
    }
    $Rupees = implode('', array_reverse($str));
    $paise = ($decimal > 0) ? " Point " . ($words[$decimal / 10] . " " . $words[$decimal % 10]) : '';
    $amount =  ($Rupees ? $Rupees : '') . $paise . ' '.getCurrencySymbol(true);
    if($negative){
        $amount = '(Minus) '. $amount;
    }
    return $amount;
}

function stockInfoColor($stock){
    if($stock < 10) return 'bg-danger';
    if($stock < 50) return 'bg-warning';
    return "";
}
function checkboxTickOrNot($value, $val_from=null){
    if($val_from=='view'){
        if($value == 1) return true; else return false;
    } else {
        if($value == 'on') return 1; else return 0;
    }
}
function getIcon($icon, $defaultIcon='ti-shine'){
    return $icon ? $icon : $defaultIcon;
}
function getPaymentPurpose($type){
    $datalist = config('constants.PAYMENT_PURPOSE');
    if(isset($datalist[$type])){
        return $datalist[$type];
    }
    return '';
}
function getPaymentModeById($id){
    $datalist = config('constants.PAYMENT_MODES');
    if(isset($datalist[$id])){
        return $datalist[$id];
    }
    return 'Cash';
}
function getPaymentOptions($isList = 'admin'){
    $datalist = config('constants.PAYMENT_MODES');
    if($isList == 'admin'){
        return $datalist;
    }
    if($isList == 'front'){
        $excludeIds = [1, 2, 3, 4, 5, 6];
        foreach ($excludeIds as $value) {
            unset($datalist[$value]);
        }
        return $datalist;
    }
}
function getBookingStatus($data){
    $status = ['Pending', 'Confirmed', 'Completed', 'Expired'];
    $statusText = 'Pending';
    $statusClass = "warning";

    $daysDiff = dateDiff($data->check_out, date('Y-m-d'), 'daysWIthSymbol');

    if($data->is_confirmed == 1){
        $statusText = $status[1];
        $statusClass = "info";

    }
    if($daysDiff < 0 && $data->is_confirmed == 0){
        $statusText = $status[3];
        $statusClass = "danger";

    }
    if($daysDiff < 0){
        $statusText = $status[2];
        $statusClass = "success";

    }
    return ['status'=>$statusText, 'statusClass'=>$statusClass];
}