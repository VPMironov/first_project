<?php
if (empty($_POST['method']))
{
	header("HTTP/1.0 404 Not Found");
	exit;
}
$method= $_POST['method'];

// список разрешённых методов
$methods_lst= array( // список разрешённых действий
	'basket', // корзина
);

if ( !in_array($method,$methods_lst) )
{
	header("HTTP/1.0 404 Not Found");
	exit;
}


// оформление заказа из корзины
if ( $method=='basket' ) {
	$ret= array('status'=> 'error');
	//подключаем корнфиг
	require_once 'config.php';
	require_once 'lib.site.php';
	require_once '../include/php_libmail.class.php';
	
	//создаем пустую запись
	$query = db()->prepare("INSERT INTO `".prefix."orders_body` (`boby`, `date`) VALUES ('', '".date('Y-m-d H:i:s')."')");
	$query->execute();
	$zakaz_id= db()->lastInsertId();
	
	//формируем данные
	$body = '<table cellpadding="10" cellspacing="0" border="0">
				<tr>
					<td>
						Бланк заказа<br />
						Сила тела<br />
						Москва,<br />
						Телефон: +7 (495) 133-98-85<br />
						info@silatela.com<br />
						www.silatela.com
					</td>
					<td>
						<table cellpadding="10" cellspacing="0" border="1">
							<tr>
								<td>Дата заказа</td>
								<td>'.date('d.m.Y').'</td>
							</tr>
							<tr>
								<td>№ заказа</td>
								<td>'.$zakaz_id.'</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>';
		$body.= '<p>Заказ составлен пользователем '.$_POST['user']['people'].'</p>';
		
		$body.= '<table cellpadding="10" cellspacing="0" id="tovbag" border="1">
						<tr>
							<td>Наименование товара</td>
							<td>Цена</td>
							<td>Количество</td>
							<td>Сумма</td>
						</tr>';
						foreach($_POST['cart'] as $row)
						{
						$body .= '<tr>';
							$body .= '<td>'.$row[0].'</td>';
							$body .= '<td><span>'.$row[1].'</span></td>';
							$body .= '<td style="text-align:center;">'.$row[2].'</td>';
							$body .= '<td><span>'.$row[1]*$row[2].'</span></td>';
						$body .= '</tr>';
						}
		$body .= '
						<tr>
							<td>Общая сумма товаров <br />без учета доставки</td>
							<td colspan="3"><span>'.$_POST['total_sum'].'</span> руб.</td>
						</tr>
				</table>';
		
		$body .= '<p> Телефон: '.$_POST['user']['phone'].'
					'.(!empty($_POST['user']['email']) ? '<br /> E-mail: '.$_POST['user']['email'] : '').'
					'.(!empty($_POST['user']['address']) ? '<br /> Адрес доставки: '.$_POST['user']['address'] : '').'
					'.(!empty($_POST['user']['comment']) ? '<br /> Комментарий: '.$_POST['user']['comment'] : '').'
					</p>';
	//обновляем данные в таблице
	$query = db()->prepare("UPDATE `".prefix."orders_body` SET `boby`=:body WHERE (`cid`='".$zakaz_id."')");
	$query->bindValue(':body', $body, PDO::PARAM_INT);
	$query->execute();
			
	//отправляем данные на почту
	try {
		$m = new Mail('utf-8');					// можно сразу указать кодировку, можно ничего не указывать ($m= new Mail;)
		$m->From('order@silatela.com');						// от кого
		if (!empty($_POST['user']['email'])) {
			$m->To($_POST['user']['email']);
			$m->Bcc('ra_242@mail.ru');
		}else {
			$m->To('ra_242@mail.ru');
		}
		$m->Subject( 'Заказ c мобильного приложения Сила Тела');
		$m->Body($body, 'html');
		$m->Priority(4) ;	// установка приоритета

		if( $m->Send() )
		{
			$ret['status']= 'accepted';
			$ret['orderID']= $zakaz_id;
		}
	}
	catch( Exception $e ) {
		$ret['status'] = $e->getMessage();
	}
	
	
// ответ с результатом работы
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: application/json');
	echo json_encode($ret);
}
?>