<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
	require $_SERVER['DOCUMENT_ROOT']."/bitrix/modules/main/include.php";

function send_wish ($event, $lid, $event_id = false)
{
	CModule::IncludeModule('sale');

	$time_old = time()-60*60*24*30;  // 30 дней назад
	$time_old = $time_old - ($time_old % (60*60*24)); // в 00:00:00

	$arItems = array();
	$arUsersItems = array();
	$rsBasket = CSaleBasket::GetList(array(),array('DELAY' => 'Y','ORDER_ID' => false, '!USER_ID' => false, '>=DATE_UPDATE' => date($DB->DateFormatToPHP(CSite::GetDateFormat("FULL", SITE_ID)),$time_old)),false,false); // выбираем отложенные товары зарегистрированных пользователей за последние 30 дней

	if($rsBasket->SelectedRowsCount())
	{
		while($arItem = $rsBasket->Fetch())
		{
			$arItems[$arItem['PRODUCT_ID']] = 1;
			$arUsersItems[$arItem['USER_ID']][$arItem['PRODUCT_ID']] = $arItem;
		}
		$rsOrders = CSaleBasket::GetList(array(),array('!ORDER_ID' => false, 'USER_ID' => array_keys($arUsersItems), 'PRODUCT_ID' => array_keys($arItems), '>=DATE_UPDATE' => date($DB->DateFormatToPHP(CSite::GetDateFormat("FULL", SITE_ID)),$time_old)),false,false); // проверяем наличие найденных отложенных товаров в заказах за последние 30 дней
		if($rsOrders->SelectedRowsCount())
		{
			while($arOrderItem = $rsOrders->Fetch())
			{
				if(isset($arUsersItems[$arOrderItem['USER_ID']][$arOrderItem['PRODUCT_ID']])) // если в отложенных у пользователя лежит товар, который он заказывал в последние 30 дней
					unset($arUsersItems[$arOrderItem['USER_ID']][$arOrderItem['PRODUCT_ID']]); // то убираем из вишлиста
			}
		}
		$rsUsers = CUser::GetList($by='ID',$sort='ASC',array('ID' => implode('|',array_keys($arUsersItems))),array('FIELDS'=>array('ID','NAME','LAST_NAME','EMAIL'))); // достанем необходимую информацию по пользователям, в частности: E-mail, имя, фамилия
		while($arUser = $rsUsers->Fetch())
		{
			$arEventFields = array(
				'NAME' => $arUser['NAME'],
				'LAST_NAME' => $arUser['LAST_NAME'],
				'EMAIL' => $arUser['EMAIL'],
				'WISH_LIST' => array()
			);
			foreach($arUsersItems[$arUser['ID']] as $arItem) // набиваем список вишлиста пользователя его отложенными товарами
				$arEventFields['WISH_LIST'][] = $arItem['NAME'];
			if($event_id) // если в парамерах функции указан конретный почтовый шаблон, отправляем по нему
				CEvent::Send($event, $lid, $arEventFields, $event_id); // регистрируем на последующую отправку
			else // иначе отправляем по всем шаблонам указанного типа почтового события
				CEvent::Send($event, $lid, $arEventFields); // регистрируем на последующую отправку
				
		}
	}
	else
		return true;
}

$params = array(
    'e:'   => 'event:',
    'l:'    => 'lid:',
    'i::'   => 'eventid::',
);

$options = getopt( implode('', array_keys($params)), $params );

if (isset($options['event']) || isset($options['e']))
{
    $event = isset( $options['event'] ) ? $options['event'] : $options['e'];
}
else
{
	$errors[] = 'event required';
}
if (isset($options['lid']) || isset($options['l']))
{
    $lid = isset( $options['lid'] ) ? $options['lid'] : $options['l'];
}
else
{
	$errors[] = 'lid required';
}

if (isset($options['event_id']) || isset($options['i']))
{
    $event_id = isset( $options['event_id'] ) ? $options['event_id'] : $options['i'];
}
else
	$event_id = false;

if ( $errors )
{
	$help .= 'Errors:' . PHP_EOL . implode("\n", $errors) . PHP_EOL;
}
die($help);

send_wish ($event, $lid, $event_id);

/*

	для работы скрипта необходимо создать почтовое событие вида:
	
	Добрый день, #NAME# #LAST_NAME#, В вашем вишлисте хранятся товары #WISH_LIST#.
	
	отправляемое на адрес #EMAIL#, и указать его в параметрах вызова скрипта:
	
	-e	-тип почтового события
	-l	-ID сайта
	-i 	-ID почтового шаблона (не обязательное)