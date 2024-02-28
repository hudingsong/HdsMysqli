<?php
 /*
 * 数据库操作
 * ============================================================================
 * 版权所有:冬夜微风，并保留所有权利。
 * 联系QQ: 28465712 
 * 版本：1.0.20210523
 * ============================================================================
*/
if(!defined('IN_HDS')) exit('Access Denied!');
class HdsMysqli{
    public $linkid = null;
    public $pre = "";
    public $table = "";
    public $where_array = [];
    public $order_array = [];
    public $order_raw = "";
    public $group_str = "";
    public $field_str = "*";
    public $limit_start = 0;
    public $limit_num = 0;
    public $join_table = "";
    public $join_id = "";
    public $original_id = "";
    public $mysql_type = MYSQLI_ASSOC;
    public $lastSql = "";
    public function __construct(array $db_config){
        $this->get_conn($db_config);        
    }
    public function get_conn(array $db_config){
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);        
        $dbcharset = $db_config["charset"]?strtolower(str_replace('-', '', $db_config["charset"])):"utf8";
        $this->pre = $db_config["pre"];
        $this->linkid = new mysqli($db_config["dbhost"], $db_config["dbuser"], $db_config["dbpass"],$db_config["dbname"]);
        if (mysqli_connect_errno($this->linkid)){
			$this->showError("Can't Connect MySQL Server(".$db_config["dbhost"].")!");
			return false;
		}
        $this->linkid->set_charset($dbcharset);       
    }

    public function name($table){
        $this->table = $table;
        return $this;
    }

    public function getAllName($table){
        return "`".$this->pre.$table."`";
    }

    public function field($arr=""){
        if($arr==""){return $this;}
        $this->field_str= $arr;
        return $this;
    }

    public function join($join_name,$join_id,$original_id){
        $this->join_name = $join_name;
        $this->join_id = $join_id;
        $this->original_id = $original_id;
        return $this;
    }
    public function whereRaw($str){
        $this->where_array[] = $str;
        return $this; 
    }
    public function where(...$args){
        $as_name = $this->join_name?" a.":"";
        $numargs = count($args);
        if($args[0]==null){ 
            return $this; 
        }
        if($numargs==3 && !is_array($args[0])){
            if(strtoupper($args[1])=="IN" || strtoupper($args[1])=="NOT IN"){
                $this->where_array[] = "(".$as_name."`".$args[0]."` " . $args[1]. " ".$args[2].")";
            }else{
                $this->where_array[] = "(".$as_name."`".$args[0]."` " . $args[1]. " '".$args[2]."')";
            }            
            return $this;
        } 
        $str = "(";
        $fg = "";             
        for($n=0; $n<$numargs; $n++){
            if(is_array($args[$n][0])){
                for($m=0; $m<count($args[$n]); $m++){
                    if(strtoupper($args[$n][$m][1])=="IN" || strtoupper($args[$n][$m][1])=="NOT IN"){
                        $str .= $fg .$as_name."`".$args[$n][$m][0]."` " . $args[$n][$m][1]. " ".$args[$n][$m][2];
                    }else{
                        $str .= $fg .$as_name."`".$args[$n][$m][0]."` " . $args[$n][$m][1]. " '".$args[$n][$m][2]."'";
                    }
                    $fg = " AND "; 
                }
            }else{
                if(strtoupper($args[$n][1])=="IN" || strtoupper($args[$n][1])=="NOT IN"){
                    $str .= $fg . $as_name."`".$args[$n][0]."` " . $args[$n][1]. " ".$args[$n][2];
                }else{
                    $str .= $fg . $as_name."`".$args[$n][0]."` " . $args[$n][1]. " '".$args[$n][2]."'";
                }
                $fg = " AND ";
            }
        }
        $str .=")";
        $this->where_array[] = $str;
        return $this;        
    }

    public function whereOr(...$args){
        $as_name = $this->join_name?" a.":"";
        $numargs = count($args);
        if(!$numargs){ return $this; }
        if(!is_array($args[0])){
            if(strtoupper($args[1])=="IN" || strtoupper($args[1])=="NOT IN"){
                $this->where_array[] = "(".$as_name."`".$args[0]."` " . $args[1]. " ".$args[2].")";
            }else{
                $this->where_array[] = "(".$as_name."`".$args[0]."` " . $args[1]. " '".$args[2]."')";
            } 
            return $this;
        }
        $str = "(";
        $fg = "";             
        for($n=0; $n<$numargs; $n++){
            if(is_array($args[$n][0])){
                for($m=0; $m<count($args[$n]); $m++){
                    if(strtoupper( $args[$n][$m][1])=="IN" || strtoupper( $args[$n][$m][1])=="NOT IN"){
                        $str .= $fg .$as_name."`".$args[$n][$m][0]."` " . $args[$n][$m][1]. " ".$args[$n][$m][2];
                    }else{
                        $str .= $fg .$as_name."`".$args[$n][$m][0]."` " . $args[$n][$m][1]. " '".$args[$n][$m][2]."'";
                    }
                    $fg = " OR "; 
                }
            }else{
                if(strtoupper($args[$n][1])=="IN" || strtoupper($args[$n][1])=="NOT IN"){
                    $str .= $fg . $as_name."`".$args[$n][0]."` " . $args[$n][1]. " ".$args[$n][2];
                }else{
                    $str .= $fg . $as_name."`".$args[$n][0]."` " . $args[$n][1]. " '".$args[$n][2]."'";
                }
                $fg = " OR ";
            }
        }
        $str .=")";
        $this->where_array[] = $str;
        return $this;          
    }
    public function find_in_set($arr, $field){
        if($arr){
            $arr = is_array($arr) ? $arr : [$arr];
            $as_name = $this->join_name?" a.":"";
            $return_str = "("; //FIND_IN_SET('4',type)
            $fg = "";            
            foreach($arr as $v){
                $return_str .= $fg . "FIND_IN_SET(".$v.",". $as_name."`".$field."`) ";
                $fg = " OR ";
            }            
            $return_str .= ")";
            $this->where_array[] = $return_str;
        }
        return $this; 
    }
    public function order($field="",$value="ASC"){
        if($field==""){return $this;}
        $this->order_array[] = [$field,$value];
        return $this;
    } 
    public function orderRaw($str){
        $this->order_raw = $str;
        return $this;
    }
    public function group($field){
        $this->group_str = $field;
        return $this;
    }

    public function limit($num, $start=0){
        $this->limit_start = $start;
        $this->limit_num = $num;
        return $this;
    }
    public function get_table(){
        if($this->join_name){
            return "`".$this->pre.$this->table."` as a, `".$this->pre.$this->join_name."` as b ";
        }else{
            return "`".$this->pre.$this->table."`";
        }
    }
    public function get_where(){ 
        $where_str = "";
        if($this->where_array){
            $fg = "";
            $where_str .= " WHERE ";
            foreach($this->where_array as $v){   
                $where_str .= $fg .$v;
                $fg = " AND ";                
            }
        }
        if($this->join_name && $this->join_id && $this->original_id){
            $where_str=$where_str?$where_str." AND a.".$this->original_id." = b.".$this->join_id:" WHERE a.".$this->original_id."=b.".$this->join_id;
        }
        return $where_str;        
    }
    public function get_order(){
        $as_name = $this->join_name?" a.":"";
        $order_str = "";
        if($this->order_array){
            $fg = "";
            $order_str .= " ORDER BY ";
            foreach($this->order_array as $v){
                $order_str .= $fg . $as_name."`".$v[0]."` " ." ". $v[1];
                $fg = " , ";
            }
        }
        if($this->order_raw){
            $order_str = " ORDER BY ".$this->order_raw;
        }
        return $order_str;
    }
    public function get_group(){
        $as_name = $this->join_name?" a.":"";
        return $this->group_str ? " GROUP BY ".$as_name."`". $this->group_str ."`" : "";
    }
    public function get_limit(){
        if($this->limit_num > 0){
            return  " LIMIT ".$this->limit_start.",".$this->limit_num;
        }else{
            return "";
        }        
    }
    public function clear(){
        $this->table = "";
        $this->where_array = [];
        $this->order_array = [];
        $this->order_raw = "";
        $this->group_str = "";
        $this->limit_start = 0;
        $this->limit_num = 0;
        $this->field_str = "*";
        $this->join_table = "";
        $this->join_id = "";
        $this->original_id = "";
    }
    private function query($sql){        
        $sql=Utils::checkSql($sql);
        try{
            $this->lastSql = $sql;
            $query = $this->linkid->query($sql);            
            $this->clear();
        } catch (Exception $e){
            $this->showError();
        } 
        return $query;
    }

    /**
     * 最后一次执行的sql语句
     */
    public function getLastSql(){
        return $this->lastSql;
    }

    /**
     * 原生查询
     */
    public function queryRaw($sql){
        $query = $this->query($sql);
        while($row = mysqli_fetch_array($query,$this->mysql_type)){
    		$rows[] = $row;
    	}
        return Utils::htmlspecialchars_($rows);
    }

    /**
     * 查询多条记录
     */    
    public function select($raw=false){
        if(!$this->table){
            $this->showError("没有选择数据表!");
			return false;
        }
        $sql = "SELECT ".$this->field_str." FROM ".$this->get_table().$this->get_where().$this->get_group().$this->get_order().$this->get_limit();
        //echo $sql."<br/>";
        $query = $this->query($sql);
        while($row = mysqli_fetch_array($query,$this->mysql_type)){
    		$rows[] = $row;
    	}
        if($raw){
            return $rows;
        }else{
            return Utils::htmlspecialchars_($rows);
        }
    }

    /**
     * 获取总记录数
     */
    public function get_num_rows(){
        if(!$this->table){
            $this->showError("没有选择数据表!");
			return false;
        }
        $sql = "SELECT ".$this->field_str." FROM ".$this->get_table().$this->get_where().$this->get_group().$this->get_order().$this->get_limit();
        return $this->query($sql)->num_rows;
    }

    public function getone($sql,$raw=false){
        /*        
        $sql=Utils::checkSql($sql);
        try{
            $query = $this->linkid->query($sql); 
            $this->clear();
        } catch (Exception $e){
            $this->showError();
        }
        */
        $query = $this->query($sql); 
        $row = mysqli_fetch_array($query, $this->mysql_type);
		if($row){
			foreach ($row as $key => $value) {
			   $row[$key]=$value;
			}
		}
        if($raw){
            return $row;
        }else{
            return Utils::htmlspecialchars_($row);
        }    	
    }

    /**
     * 查询单条记录
     */
    public function find($id=0, $field="id", $raw=false){   
        if(!$this->table){
            $this->showError("没有选择数据表!");
			return false;
        }     
        if($id){
            $sql = "SELECT ".$this->field_str." FROM ".$this->get_table()." WHERE ".$field." = ".$id.$this->get_group().$this->get_order()." LIMIT 1";
        }else{
            $sql = "SELECT ".$this->field_str." FROM ".$this->get_table().$this->get_where().$this->get_group().$this->get_order()." LIMIT 1";
        }
        return $this->getone($sql,$raw);
    }

    /**
     * 返回指定的字段值
     */
    public function findByField($field="id"){   
        if(!$this->table){
            $this->showError("没有选择数据表!");
			return false;
        }
        $sql = "SELECT ".$this->field_str." FROM ".$this->get_table().$this->get_where().$this->get_group().$this->get_order()." LIMIT 1";
        $row = $this->getone($sql);
        return $row[$field];
    }
    
     
    /**
     * 获取统计总数
     */
    function get_nums($field="*",$type='count'){
        if(!$this->table){
            $this->showError("没有选择数据表!");
			return false;
        }
        $sql = "SELECT ".$type."(".$field.") as num FROM ".$this->get_table().$this->get_where().$this->get_group()." LIMIT 1";
        //return $sql;
        $row = $this->getone($sql);
        return $row["num"];
    }

    /**
     * 获取单条记录的某个字段
     */
    function get_row_field($field){
        if(!$this->table){
            $this->showError("没有选择数据表!");
			return false;
        }
        $sql = "SELECT ".$field." FROM ".$this->get_table().$this->get_where().$this->get_group().$this->get_order()." LIMIT 1";
        $row = $this->getone($sql);
        return $row[$field];
    }

    /**
     * 插入数据
     */
    public function insert($insertsqlarr, $returnid=1, $replace = false){
        if(!$this->table){$this->showError("没有选择数据表!");}
        $insertkeysql = $insertvaluesql = $comma = '';
        foreach ($insertsqlarr as $insert_key => $insert_value) {
            $insertkeysql .= $comma.'`'.$insert_key.'`';
            if($insert_value=="null" || $insert_value=="NULL"){
                $insertvaluesql .= $comma.''.$insert_value.'';
            }else{
                $insertvaluesql .= $comma.'\''.$insert_value.'\'';
            }
            $comma = ', ';
        }
        $method = $replace?'REPLACE':'INSERT';  
        //$state = $this->linkid->query($method." INTO ".$this->get_table()." ($insertkeysql) VALUES ($insertvaluesql)");
        $state = $this->query($method." INTO ".$this->get_table()." ($insertkeysql) VALUES ($insertvaluesql)");
        if($returnid && !$replace) {
            return $this->linkid->insert_id;
        }else {
            return $state;
        }
    }  

    /**
     * 更新数据
     */
    public function update($setsqlarr){
        if(!$this->table){$this->showError("没有选择数据表!");}
        $setsql = $comma = '';
        foreach ($setsqlarr as $set_key => $set_value) {
            if($set_value === "++"){
                $setsql .= $comma.'`'.$set_key.'` = `'.$set_key.'` + 1';
            }elseif($set_value === "--"){
                $setsql .= $comma.'`'.$set_key.'` = `'.$set_key.'` - 1';
            }else{
                if(is_array($set_value)) {
                    if($set_value[0]=="null" || $set_value[0]=="NULL"){
                        $setsql .= $comma.'`'.$set_key.'`'.'='.$set_value[0].'';
                    }else{
                        $setsql .= $comma.'`'.$set_key.'`'.'=\''.$set_value[0].'\'';
                    }                    
                } else {
                    if($set_value=="null" || $set_value=="NULL"){
                        $setsql .= $comma.'`'.$set_key.'`'.'='.$set_value.'';
                    }else{
                        $setsql .= $comma.'`'.$set_key.'`'.'=\''.$set_value.'\'';
                    }
                }
            }            
            $comma = ', ';
        }        
        //if($this->linkid->query("UPDATE ".$this->get_table()." SET ".$setsql.$this->get_where())){
        if($this->query("UPDATE ".$this->get_table()." SET ".$setsql.$this->get_where())){
            return $this->linkid->affected_rows;
        }else{
            return -1;
        }        
    }   
  
    /**
     * 删除数据
     */
    public function delete(){
        if(!$this->table){$this->showError("没有选择数据表!");}
        //if($this->linkid->query("DELETE FROM ".$this->get_table().$this->get_where())){
        if($this->query("DELETE FROM ".$this->get_table().$this->get_where())){
            return $this->linkid->affected_rows;
        }else{
            return -1;
        }
    }
    /**
     * 开启事务
     */
    public function start(){
        $this->linkid->autocommit(false);
    }
    /**
     * 回滚事务
     */
    public function rollback(){
        $this->linkid->rollback();
    }
    /**
     * 提交事务
     */
    public function commit(){
        $this->linkid->commit();
    }
    /**
     * 关闭资源
     */
    public function close(){
    	return mysqli_close($this->linkid);
    }
    /**
     * 显示错误信息
     */
    public function showError($msg=""){
        if($msg){
            $info = "Mysql Error：".$msg;			
        }else{
            $info = "Mysql Error：[ ".mysqli_errno($this->linkid)." ] ".mysqli_error($this->linkid);
        }
        exit($info);
    }
    /**
     * 显示版本信息
     */
    function get_version(){
        $row = $this->getone("select VERSION() as v");
        return $row["v"];	        
    }
    /**
     * 记录转str
     */
    public static function rows_to_str($rows, $field){
        $return_arr = array();
        foreach((array)$rows as $row){
            $return_arr[] = $row[$field];
        }
        return count($return_arr)?implode(",",$return_arr):"";
    }

     /**
     * 旧版
     */
    function fetch_array($result){
		return mysqli_fetch_array($result,$this->mysql_type);
	}
    /**************************待弃用 deprecated**************************/
    /**
     * 获取最新插入的主键
     */
    public function insert_id(){
    	return $this->linkid->insert_id;
    }
    /**
     * 获取上一次操作影响的行数
     */
    function affected_rows(){
        return $this->linkid->affected_rows;
    }
}
?>