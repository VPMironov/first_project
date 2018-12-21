<?
function db() {
	static $db;
	global $db_host, $db_user, $db_pass, $db_name, $db_charset;
	try{
		if(is_null($db)) {
			$db_options = array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '.$db_charset);
			$db = new PDO('mysql:host='.$db_host.';dbname='.$db_name, $db_user, $db_pass, $db_options);
		}
		return $db;
	}catch(Exception $e) {
		if($_SERVER['REMOTE_ADDR'] == '87.244.23.82')
		{
			echo 'Ошибка подключения к БД:<br />'.$e->getMessage();
		}
		else
		{
			echo 'Ошибка подключения к БД.';
		}
    	exit();
    }
}
/*получаем информацию о странице по id*/
function get_info($select,$bd_name,$cid)
{
	$query = db()->prepare('select '.$select.' from `'.prefix.''.$bd_name.'` WHERE `cid` = :cid');
	$query->bindParam(':cid', $cid, PDO::PARAM_INT);
	$query->execute();
	$result= $query->fetch(PDO::FETCH_ASSOC);
	return $result;
}
/*получаем информацию о странице по pid*/
function get_menu($select,$bd_name,$pid)
{
	$query = db()->prepare('select '.$select.' from `'.prefix.''.$bd_name.'` WHERE `is_vis`="yes" AND `pid`="'.$pid.'" ORDER BY `position`');
	$query->execute();
	$result= $query->fetchALL(PDO::FETCH_ASSOC);
	return $result;
}
// Поиск по товарам
function get_search($select,$bd_name,$bd_post)
{
	$query = db()->prepare('select '.$select.' from `'.prefix.''.$bd_name.'` WHERE `is_vis`="yes" AND '.$bd_post.'');
	$query->execute();
	$result= $query->fetchALL(PDO::FETCH_ASSOC);
	return $result;
}
//список товаров
	//получаем список вложенных подразделов
	function GetParent($id)
	{	
		$result	= '';
		
		$cat_query = db()->query("SELECT SQL_CALC_FOUND_ROWS `cid`, `pid` FROM `".prefix."catalogs` WHERE `pid`='".$id."' AND `archive`='0'");

		$rows_count_q = db()->query("SELECT FOUND_ROWS() total");
		$col = $rows_count_q->fetch(PDO::FETCH_NUM);

		if($col[0] > 0)
		{
			while($cat =$cat_query->fetch(PDO::FETCH_ASSOC))
			{
				$result .= ', '.$cat['cid'];

				$result .= GetParent($cat['cid']);
			}
		}
		return $result;
	}
	
	//получаем текущий подраздел
	function GetCurrent($id, $tovars = array())
	{
		$return = array();
		$prod_list = array();
		$count = 0;
		
		$query = db()->prepare("SELECT `cid`, `pid`, `name`, `h1` FROM `".prefix."catalogs` WHERE `cid`='".$id."' AND `is_vis`='yes' AND `archive`='0' ORDER BY `position`");
		$query->execute();
		$result = $query->fetch(PDO::FETCH_ASSOC);

		if(!empty($tovars))
		{
			foreach($tovars as $value)
			{
				//если есть вложенные товары, добавляем их в массив
				if(!empty($tovars[$result['cid']]))
				{
					$prod_list = $value;	
				}
				
				//считаем общее количество товаров из всех вложенных подразделов
				$count += count($value);
			}
		}
		
		$return['cid']  = $result['cid'];
		$return['pageName']  = $result['name'];
		$return['pageTitle'] = (!$result['h1'] ? $result['name'] : $result['h1']);
		$return['count']['pageCount'] = $count;	
		if(!empty($prod_list))
		{
			$return['product_list'] = $prod_list;
		}
		
		return $return;
	}
	
	//получаем массив вложенных подразделов
	function GetCat($list)
	{
		$return = array();
		
		$query = db()->prepare("SELECT `cid`, `pid`, `name` as `CatalogName` FROM `".prefix."catalogs` WHERE `cid` IN (".$list.") AND `is_vis`='yes' AND `archive`='0' ORDER BY `position`");
		$query->execute();
		$result = $query->fetchALL(PDO::FETCH_ASSOC);
		
		if(!empty($result))
		{
			foreach($result as $value)
			{
				$return[$value['cid']] = $value;
			}
		}
		
		return $return;
	}
	
	//получаем массив товаров
	function GetTov($list)
	{
		$return = array();
		
		$query = db()->prepare("SELECT `cid`, `pid`, `name` as `productName`, `price` as `productPrice` FROM `".prefix."objects` WHERE `pid` IN (".$list.") AND `is_vis`='yes' AND `archive`='0' ORDER BY `position`");
		$query->execute();
		$result = $query->fetchALL(PDO::FETCH_ASSOC);
		
		if(!empty($result))
		{
			foreach($result as $value)
			{
				$return[$value['pid']][] = $value;
			}
		}
		
		return $return;	
	}
	
	//строим дерево из подразделов
	function BuildTree($elements = array(), $tovars = array(), $parentId = 0) {

		$branch = array();

		foreach ($elements as $element) 
		{
			if ($element['pid'] == $parentId) 
			{
				$children = BuildTree($elements, $tovars, $element['cid']);
				
				if ($children) 
				{
					$element['SubCatalog'] = $children;
				}
				
				if(!empty($tovars[$element['cid']]))
				{
					$element['count']['pageCount'] = count($tovars[$element['cid']]);
					$element['product_list'] = $tovars[$element['cid']];
				}
				
				$branch[$element['cid']] = $element;
				
				unset($element);
			}
		}

		return $branch;
	}

/*
function get_all_tov($select, $pid){
	$return_lst = array();
	$query = db()->prepare('select `cid` from `'.prefix.'catalogs` WHERE `is_vis`="yes" AND `cid`="'.$pid.'" ORDER BY `position`');
	$query->execute();
	$cur_cat= $query->fetchALL(PDO::FETCH_ASSOC);
	if(count($cur_cat) >0){
		$query = db()->prepare('select '.$select.' from `'.prefix.'objects` WHERE `is_vis`="yes" AND `pid`="'.$cur_cat[0]['cid'].'" ORDER BY `position`');
		$query->execute();
		$cur_tov= $query->fetchALL(PDO::FETCH_ASSOC);
		
		if(count($cur_tov) >0){
			$i=1;
			foreach($cur_tov as $row){
				$rec['cid']= $row['cid'];
				$rec['productName']= $row['name'];
				$rec['productPrice']= $row['price'];
				$return_lst['product_list'][$i] = $rec;
				$rec_count['pageCount'] = $i;
				$return_lst['count'] = $rec_count;
				$i++;
			}
		}
	}
	return $return_lst;
}

//получаем информацию по странице
function get_catalog_tov($select, $bd_name, $pid)
{
	$return_lst = array();
	$item_main_sub   = get_menu('`name`, `cid`','catalogs',$pid);
	//print_r($item_main_sub);
	if (!empty($item_main_sub)){
		$i=1;
		foreach($item_main_sub as $row){
			$rec_sub['CatalogName']= $row['name'];
			$return_lst['SubCatalog'][$i] = array_merge($rec_sub, get_all_tov($select, $row['cid']));
			//$return_lst['SubCatalog'][$i] = get_all_tov($select, $row['cid']);
			$i++;
		}
	}
	else{
		$return_lst = get_all_tov($select, $pid);
	}
	return $return_lst;
}
*/
?>