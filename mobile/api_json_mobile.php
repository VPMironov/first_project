<?
//$method= 'list_product';

if (empty($_POST['method']))
{
	header("HTTP/1.0 404 Not Found");
	exit;
}
$method= $_POST['method'];

// список разрешённых методов
$methods_lst= array(
	'pages', // Получение информацию о странице
	'catalog', // Получение информацию о разделе
	'product', // Получение информацию о товаре
	'list_menu', // Получение списка меню
	'list_product', // Получение списка товаров
	'search', // Поиск по товарам
);
if ( !in_array($method,$methods_lst) )
{
	header("HTTP/1.0 404 Not Found");
	exit;
}

// заголовки
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

//подключаем корнфиг
require_once 'config.php';
require_once 'lib.site.php';

$return_lst= array();

if ( $method=='pages' )
{
	$pages_id = $_POST['pages_id'];
	$select='`name`, `tith1`, `description`';
	$item_main   = get_info($select,'pages',$pages_id);
	if (!empty($item_main)) {
		// поиск и замена картинок
		$description = str_replace('src="/', 'src="https://www.silatela.com/', $item_main['description']);
		// передаем информацию о странице
		$rec['pageTitle']= $item_main['name'];
		$rec['description']= '<h1>'.(!$item_main['tith1'] ? $item_main['name'] : $item_main['tith1']).'</h1>'.$description.'';
		$return_lst['page_info'] = $rec;
		
		//передаем вложенность
		$bd_name = $method;
		$pid = $pages_id;
		$select = '`cid`, `name`, `tith1`, `description`';
		$item_sub_menu   = get_menu($select,$bd_name,$pid);
		if (!empty($item_sub_menu)) {
			$i=0;
			foreach($item_sub_menu as $row){
				// поиск и замена картинок
				$description = str_replace('src="/', 'src="https://www.silatela.com/', $row['description']);
				//астивный элеменет
				if($i==0) {$rec_sub['active'] = 'true';} else {$rec_sub['active'] = '';}
				$rec_sub['cid']= $row['cid'];
				$rec_sub['pageName']= $row['name'];
				$rec_sub['pageTitle']= ''.(!$row['tith1'] ? $row['name'] : $row['tith1']).'';
				$rec_sub['pageDescription']= $description;
				$return_lst['sub_menu'][$i] = $rec_sub;
				$i++;
			 }
		}
	}
	else
	{
		$return_lst= array('Error'=>'Something is wrong!');
	}
	
}
elseif ( $method=='list_menu' )
{
	$bd_name = $_POST['pages_id'];
	$pid = '0';
	$select = '`cid`, `name`';
	$item_main   = get_menu($select,$bd_name,$pid);
	if (!empty($item_main)) {
		
		$i=0;
		 foreach($item_main as $row){
			//$return_lst['menu']
			$rec['cid']= $row['cid'];
			$rec['pageName']= $row['name'];
			//$rec['href']= 'pages.html?id='.$row['cid'].'';
			
			
			$return_lst['menu'][$i] = $rec;
			//$return_lst[$i] = $rec;
			$i++;
		 }
		
		//$return_lst['OK']= true;
	}
	else
	{
		$return_lst= array('Error'=>'Something is wrong!');
	}
}
elseif ( $method=='catalog' )
{
	$bd_name = $_POST['pages_id'];
	$pid = '0';
	$select = '`cid`, `name`, `name_menu`';
	$item_main   = get_menu($select,$bd_name,$pid);
	if (!empty($item_main)) {
		
		$i=1;
		 foreach($item_main as $row){
			$rec['cid']= $row['cid'];
			$rec['pageName']= ''.(!$row['name_menu'] ? $row['name'] : $row['name_menu']).'';
			
			$return_lst['menu'][$i] = $rec;
			
			$rec_count['pageCount'] = $i;
			$return_lst['count'] = $rec_count;
			$i++;
		 }	
	}
	else
	{
		$return_lst= array('Error'=>'Something is wrong!');
	}
}
elseif ( $method=='list_product' )
{
	$bd_name = $_POST['pages_db'];
	$pid = $_POST['pages_id'];
	
	//список вложенных подразделов
	$parent_list = GetParent($pid);
	//массив вложенных подразделов
	$arr_cat 	 = (GetCat($pid.$parent_list));
	//массив товаров
	$arr_tov	 = (GetTov($pid.$parent_list));
	//массив с текущей страницей
	$current 	 = GetCurrent($pid, $arr_tov);
	//дерево подразделов
	$tree		 = BuildTree($arr_cat, $arr_tov, $pid);
	//соединяем массив с текущей страницей и дероево подразделов
	if(!empty($tree))
	{
		$current['SubCatalog'] = $tree;
	}
	
	$return_lst = $current;
}
elseif ( $method=='product' )
{
	$pages_id = $_POST['pages_id'];
	$select='`cid`,`pid`,`name`, `h1`, `descr`, `descr2`, `price`';
	$item_main   = get_info($select,'objects',$pages_id);
	if (!empty($item_main)) {
		// поиск и замена картинок
		$description = str_replace('src="/', 'src="https://www.silatela.com/', $item_main['descr']);
		//Получаем информацию о разделе
		$item_main_cat   = get_info('`name`','catalogs',$item_main['pid']);
		$rec['catName'] = $item_main_cat['name'];
		// передаем информацию о странице
		$rec['pageCid']= $item_main['cid'];
		$rec['pagePid']= $item_main['pid'];
		$rec['pageTitle']= $item_main['name'];
		$rec['pageName']= ''.(!$item_main['h1'] ? $item_main['name'] : $item_main['h1']).'';
		$rec['productPrice']= $item_main['price'];
		$rec['description']= $description;
		$rec['description2']= $item_main['descr2'];
		$return_lst['page_info'] = $rec;
		//похожие товары
		$select_analog = '`cid`, `name` as `productName`, `price` as `productPrice`';
		$bd_name_analog = 'objects';
		$bd_post_analog = '`pid`="'.$item_main['pid'].'" AND `cid`!="'.$pages_id.'" ORDER BY RAND() LIMIT 10';
		$item_main_analog   = get_search($select_analog,$bd_name_analog,$bd_post_analog);
		if (!empty($item_main_analog)) {
			foreach($item_main_analog as $row){
				$return_lst['analog'] = $item_main_analog;
			}
		}
		
	}
	else
	{
		$return_lst= array('Error'=>'Something is wrong!');
	}
	
}
elseif ($method=='search')
{	
	$select = '`cid`, `name` as `productName`, `price` as `productPrice`';
	$bd_name = 'objects';
	$bd_post = '`name` LIKE "%'.$_POST['pages_id'].'%" OR `h1` LIKE "%'.$_POST['pages_id'].'%" ORDER BY `position`';
	
	$item_main   = get_search($select,$bd_name,$bd_post);
	if (!empty($item_main)) {
		$i=1;
		 foreach($item_main as $row){
			$return_lst['cid'] = '1';
			$return_lst['pageName'] = $_POST['pages_id'];
			$return_lst['product_list'] = $item_main;
			$rec_count['pageCount'] = $i;
			$return_lst['count'] = $rec_count;
			$i++;
		 }
	}
	else
	{
		$return_lst= array('Error'=>'К сожалению, по вашему запросу ничего не найдено. Попробуйте изменить параметры поиска.');
	}
}
else
{
	$return_lst= array('Error'=>'Something is wrong!');
}

echo json_encode($return_lst);
exit();

/*$return			= [];
$cacheTime		= 3600;
$method			= $_POST;
//$method			= $_GET;
$methodList		= array(
							'catalogs', // разделы
							'pages', // страницы
						);
*/
/*
if(!empty($method['method']) && in_array($method['method'], $methodList))
{
	switch($method['method'])
		{
			case 'catalogs':
				$return = '1';
			break;
			
	
			case 'pages':
				$return = '3';
			break;
			
			default:
			break;
		}
	
	if(!empty($return))
		{
			header('Access-Control-Allow-Origin: *');
			header('Content-Type: application/json');
			
			$return			= ['data'=>$return];
			$return['OK']	= true;
			
			echo json_encode($return);
			exit();
		}
		else
		{
			echo json_encode(['status'=>'fail']);
			exit();
		}
}*/
?>