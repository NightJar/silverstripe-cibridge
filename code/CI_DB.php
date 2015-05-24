<?php

class CI_DB extends Object {

	private $conn;
	private $query;
	private $setToManipulate = array();
	
	public function __construct($conn) {
		parent::__construct();
		$this->conn = $conn;
		$this->query = new SQLQuery('');
	}

	/*
		DB_Driver stuff
	*/
	
	public function query($sql, $binds=false, $return_object=true) {
		return $this->execute($sql); //SS_Query implements Iterator
		//I don't even know what binds do.
	}
	public function last_query() {
		return DB::$lastQuery;
	}
	public function escape($data) {
		return $this->escape_like_str($data, false);
	}
	public function escape_str($data) {
		return $this->escape_like_str($data, false);
	}
	public function escape_like_str($data,$like=true) {
		if($data === false) $data = 0;
		$escaped = $data === null ? 'NULL' : Convert::raw2sql($data);
		return $like ? str_replace(array('%','_'), array('\\%','\\_'), $escaped) : '\''.$escaped.'\'';
	}
	
	/*
		'Active Record' stuff
	*/
	
	//select
	public function select($select='*') {
		$matches = array();
		
		$brackets = '\s*(?P<brackets>\((?>[^()]|(?P>brackets))*\))';
		$squo = '\s*\'(?>[^\']|(?<=\\\\)\')+\'';
		$dquo = '\s*"(?>[^"]|(?<=\\\\)")*"';
		$quotes = "$squo|$dquo";
		$operator = '(?>\s*(?>[-+*/%=<>]|![=<>]|<[>=]|>=|==|AND|OR|(?>NOT )?BETWEEN|(?>NOT )?IN|(?>NOT )?LIKE)\s*)'; //does not support subquery things such as EXISTS, ALL, or ANY
		$as = '(?>\s+as|AS)?(?>\s+[\'"]?(?P<alias>[a-z0-9_-]+)[\'"]?)?';
		$end = ',?\s?';
		$start = '(?>[^,(\s\'"]+)';
		
		//nasty, but it ... mostly... works. Hasn't had full testing.
		//MySQL using ' and " interchangeably can cause issue (because we're executing ANSI SQL
		//eg. A CONCAT with the _string_ " " is not a valid column name).
		preg_match_all("#\s*(?P<statement>$start?(?>$operator?(?>$brackets|$quotes|$start))*)$as$end#i", $select, $matches);
		foreach($matches[0] as $idx => $line) {
			$line = $matches['statement'][$idx];
			$alias = $matches['alias'][$idx];
			if($line)
				$this->query->selectField($line, $alias);
		}
		return $this;
	}
	public function distinct($yn) {
		$this->query->setDistinct($yn);
		return $this;
	}
	
	//from
	public function from($from) {
		$this->query->addFrom($from);
		return $this;
	}
	public function join($table, $on, $type=null) {
		$alias = null;
		if(preg_match('#(?> as)? [\'"]?([a-z0-9_-]+)[\'"]?\s*$#i', $table, $result)) {
			$table = substr($table, 0, -1*strlen($result[0]));
			$alias = $result[1];
		}
		switch(strtolower($type)) {
			case 'left':
			case 'left outer':
				$this->query->addLeftJoin($table, $on, $alias);
				break;
			case 'right':
			case 'right outer':
				//this probably shouldn't work, but it will so whatever.
				$this->query->addFrom(array(
					$alias?:$table => array(
						'type' => 'RIGHT',
						'table' => $table,
						'filter' => array($on),
						'order' => 20
					)
				));
				break;
			default:
				$this->query->addInnerJoin($table, $on, $alias);
		}
		return $this;
	}
	
	//where & having
	public function where($field, $value=null, $escape=true) {
		return $this->wherehaving($field, $value, $escape);
	}
	private function wherehaving($field, $value=null, $escape=true, $any=false, $having=false) {
		if(is_array($field)) {
			foreach($field as $f => $v)
				$where[] = $this->hasOp($f) ? $f.$v : "$f=$v";
		}
		else {//string or some other nonsense that will cast to one
			if(is_array($value)) {
				if($escape) {
					foreach($value as $i => $v) {
						$v = Convert::raw2sql($v);
						$value[$i] = is_string($v) ? "'".trim($v, '\'"')."'" : $v;
					}
				}
				$value = '('.implode($value).')';
			}
			elseif($escape) {
				if($value === null && !$this->hasOp($field))
					$field = "$field IS NULL";
				else
					$value = Convert::raw2sql($value);
			}
			$where = $this->hasOp($field) ? array("$field$value") : array("$field = $value");
		}
		if($having) {
			$any ? $this->query->addHaving('('.implode(' OR ', $where).')') : $this->query->addHaving($where);
		}
		else {
			$any ? $this->query->addWhereAny($where) : $this->query->addWhere($where);
		}
		return $this;
	}
	private function hasOp($fieldstring) {
		return preg_match('#\s+[<>!=]{1,2}|(?>(?>not )?(?>null)|(?>like)|(?>in))$#', trim(strtolower($fieldstring)));
	}
	public function or_where($field, $value=null, $escape=true) {
		return $this->where($field, $value, $escape, true);
	}
	public function where_in($field, $values) {
		return $this->where("$field IN", $values);
	}
	public function or_where_in($field=null, $values=null) {
		return $this->where("$field IN", $values, true, true);
	}
	public function where_not_in() {
		return $this->where("$field NOT IN", $values);
	}
	public function or_where_not_in() {
		return $this->where("$field NOT IN", $values, true, true);
	}
	public function like($field, $match=null, $side='both', $not=null, $or=false) {
		if($not) $not = 'NOT ';
		$l = $r = '%';
		switch($side) {
			case 'none': $r = '';
			case 'after': $l = '';break;
			case 'before': $r = '';
		}
		return $this->where("$field {$not}LIKE", "'$l$match$r'", false, $or);
	}
	public function not_like($field, $match=null, $side='both') {
		return $this->like($field, $match, $side, true);
	}
	public function or_like($field, $match=null, $side='both') {
		return $this->like($field, $match, $side, false, true);
	}
	public function or_not_like($field, $match=null, $side='both') {
		return $this->like($field, $match, $side, true, true);
	}
	public function having($rfield, $value=null, $escape=true) {
		return $this->wherehaving($rfield, $value, $escape, false, true);
	}
	public function or_having($rfield, $value=null, $escape=true) {
		return $this->wherehaving($rfield, $value, $escape, true, true);
	}
	public function limit($limit, $offset=null) {
		$this->query->setLimit($limit, $offset);
		return $this;
	}
	//ordinal bits	
	public function group_by($by) {
		$this->query->addGroupBy($by);
		return $this;
	}
	public function order_by($orderby, $direction = '') {
		$this->query->addOrderBy($orderby, $direction);
		return $this;
	}
	/*public function offset($offset) {
		$this->isStupid.
	}*/

	//other bits
	public function set($key, $value=null, $escape=true) {
		if(!is_array($key)) {
			$key = array($key=>$value);
		}
		foreach((array)$key as $f => $v) {
			$this->setToManipulate[$escape?Convert::raw2sql($f):$f] = $escape?(is_string($v)?$this->escape($v):Convert::raw2sql($v)):$v;
		}
		return $this;
	}
	public function get($table=null, $limit=null, $offset=null) {
		if($table) $this->from($table);
		if($limit || $offset) $this->limit($limit, $offset);
		return $this->execute();
	}
	public function count_all_results($table = '') {
		
	}
	public function get_where($table=null, $where=null, $limit=null, $offset=null) {
		if($where) $this->where($where);
		return $this->get($table, $limit, $offset);
	}
	public function insert($table=null, $set=null) {
		if($table) $this->query->setFrom($table);
		if($set) $this->set($set);
		return $this->execute($this->compileManipulation('insert'));
	}
	//TODO: is this supposed to loop?
	public function insert_batch($table=null, $set=null) {
		return $this->insert($table, $set);
	}
	public function update($table=null, $set=null, $where=null, $limit=null) {
		if($table) $this->query->setFrom($table);
		if($set) $this->set($set);
		if($where) $this->where($where);
		if($limit) $this->limit($limit);
		return $this->execute(true);
	}
	private function compileManipulation($type='update') {
		$table = array_shift($this->query->getFrom());
		return array(
			$table => array(
				'fields' => $this->setToManipulate,
				'command' => $type,
				'where' => '('.implode(') '.$this->query->getConnective().' (', $this->query->getWhere()).')'
			)
		);
	}
	public function truncate($table=null) {
		if(!$table) $table = array_shift($this->query->getFrom());
		return $this->execute("TRUNCATE $table");
	}
	public function delete($table=null, $where=null, $limit=null, $reset_data=true) {
		if($table) $this->from($table);
		if($where) $this->where($where);
		if($limit) $this->limit($limit);
		return $this->execute();
	}
	private function execute($manipulation=null, $debug=false) {
		$database = DB::getConn($this->conn);
		//urgh I can't believe I'm doing this :< (return false on error - should just error IMO!)
		set_error_handler('errorCatcher', error_reporting());
		try {
			if(!$manipulation || is_string($manipulation)) {
				//so it turns out SQLQuery is a bit broken.
				
				//unset the * selector which is set by default
				$select = $this->query->getSelect();
				if(count($select) > 1) { //Surely no one uses * if they specify other fields too...
					unset($select['*']);
					unset($select['']);
					@$this->query->setSelect($select); //deprecation warning thrown kinda defeats most of the point of setSelect (especially when the method itself is NOT the deprecated part [as @ 3.1.5]) :<
				}
				
				//it's assumed that setting a from is ALWAYS done before setting joins (that $from[0] is the FROM clause)
				$joins = $this->query->getFrom();
				$froms = array();
				foreach($joins as $alias => $from) {
					if(is_string($from)) { //we've got a FROM, not a JOIN
						if(is_int($alias))
							$froms[] = $from;
						else //aliased table
							$froms[$alias] = $from;
						unset($joins[$alias]);
					}
				}
				$this->query->setFrom($froms + $joins);
				
				//So now our issues are taken care of, also turns out SQLQuery::sql uses default connection - not ideal. Could use injector to fix, but... eh. Core patch would be better.
				$sql = $manipulation ?: $this->query->sql();
				
				//Oh, except that subqueries aren't table names. This is a silly assumption SQL assmebly :<
				$sql = preg_replace('#JOIN "\(SELECT ((?>[^"]|(?<=\\\\)")+)\)" AS#i', 'JOIN (SELECT $1) AS', $sql);
				
				//because we're editing the final query string, $this->getQuery()->sql() won't do.
				if($debug) return $sql;
				
				//finally, let's make the query.
				$result = $database->query($sql);
				
				//oh, but ammend it slightly:
				$result->queryResultColumns = array_keys($select);
			}
			else {
				$manipulation = is_array($manipulation) ? $manipulation : $this->compileManipulation();
				$database->manipulate($manipulation); //doesn't return anything
				$result = true;
			}
		}
		catch(Exception $sqlerror) {
			Debug::loadErrorHandlers();
			if(Director::isDev()) throw $sqlerror;
			$result = false;
		}
		Debug::loadErrorHandlers();
		//reset
		$this->query = new SQLQuery();
		$this->setToManipulate = array();
		return $result ? CI_Result::create($result) : false;
	}
	
	//returns auto-generated ID of last INSERT
	public function insert_id() {
		return DB::getConn($this->conn)->getGeneratedID(null);
	}
	
	//debug query - only works for SELECT (ie. not manipulations)
	public function getSQL() {
		return $this->execute(null, true);
	}
	
	//return to SS methodology!
	public function getQuery() {
		return $this->query;
	}
}

/*

***Unimplemented stuff***

----- db driver

initialize()
db_set_charset($charset, $collation)
platform()
version()
load_rdriver()
simple_query($sql)
trans_off()
trans_strict($mode = TRUE)
trans_start($test_mode = FALSE)
trans_complete()
trans_status()
compile_binds($sql, $binds)
is_write_type($sql)
elapsed_time($decimals = 6)
primary($table = '')
list_tables($constrain_by_prefix = FALSE)
table_exists($table_name)
list_fields($table = '')
field_exists($field_name, $table_name)
field_data($table = '')
insert_string($table, $data)
update_string($table, $data, $where)
call_function($function)
cache_set_path($path = '')
cache_on()
cache_off()
cache_delete($segment_one = '', $segment_two = '')
cache_delete_all()
close()
display_error($error = '', $swap = '', $native = FALSE)
protect_identifiers($item, $prefix_single = FALSE)



----- 'active record' stuff

select_max($select = '', $alias = '')
select_avg($select = '', $alias = '')
select_sum($select = '', $alias = '')
set_insert_batch($key, $value = '', $escape = TRUE)
replace($table = '', $set = NULL)
update_batch($table = '', $set = NULL, $index = NULL)
set_update_batch($key, $index = '', $escape = TRUE)
empty_table($table = '')
dbprefix($table = '')
set_dbprefix($prefix = '')
start_cache
stop_cache
flush_cache



----- db specific driver stuff (made by chimps)
db_connect
db_pconnect
reconnect
db_select
trans_begin
trans_commit
trans_rollback
affected_rows
count_all
*/

class CI_Result extends Object {

	private $result;
	
	public function __construct($result) {
		parent::__construct();
		$this->result = $result;
	}
	
	/*
		Gt full result
	*/
	public function result($type='object') {
		$result = array();
		foreach($this->result as $row) {
			$result[] = in_array($type, array('array', 'object')) ? 
				($type == 'object' ? (object)$row : $row) : 
				Injector::inst()->createWithArgs($type, array($row));
		}
		return $result;
	}
	public function custom_result_object($class='ArrayData') {
		return $this->result($type);
	}
	public function result_object() {
		return $this->result('object');
	}
	public function result_array() {
		return $this->result('array');
	}
	/*
		Get single row
	*/
	public function row($n=0, $type='object') {
		$result = array();
		$this->result->seek($n); //supposed to return row, but doesn't in MySQLQuery at least.
		switch($type) {
			case 'array': $result = $this->result->current(); break;
			case 'object': $type = 'ArrayData';
			default: $result = Injector::inst()->createWithArgs($type, array($this->result->current()));
		}
		return $result;
	}
	//public function set_row($key, $value = NULL) #what even is this? Looks like idiocy.
	public function custom_row_object($n, $type) {
		return $this->row($n, $type);
	}
	public function row_object($n=0) {
		return $this->row($n, 'ArrayData');
	}
	public function row_array($n=0) {
		return $this->row($n, 'array');
	}
	public function first_row($type='object') {
		return $this->row(0, $type);
	}
	public function last_row($type='object') {
		return $this->row($this->num_rows()-1, $type);
	}
	public function next_row($type='object') {
		return $this->row($this->result->key()+1, $type);
	}
	public function previous_row($type='object') {
		return $this->row($this->result->key()-1, $type);
	}
	
	public function num_rows() {
		return $this->result->numRecords();
	}
	public function num_fields() {
		return count($this->result->first()?:null); //count(null) === 0 (no error/warning) && array() == false
	}
	public function list_fields() {
		//a bit of a hack, but whatever.
		$fields = isset($this->result->queryResultColumns) ? $this->result->queryResultColumns : null;
		if(!is_array($fields)) {
			$first = $this->result->first();
			$fields = is_array($first) ? array_keys($first) : array();
		}
		return $fields;
	} 
	#public function field_data() { return array(); } //array of database field stats (type, default, is Key, etc) for each field.
	public function free_result() {
		unset($this->result);
		return true;
	}
	
	//revert to SS methodology!
	public function getQueryResult() {
		return $this->result;
	}
}

function errorCatcher($errno, $errstr, $errfile, $errline) {
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
