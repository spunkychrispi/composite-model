<?php

require_once 'Zend/Db/Table.php';

/*

This class can be used to store and load a table and its related data from the database. It uses the _dependentTables and
_referenceMap fields that should be set for each Zend_Db_Table class, however, the _dependentTables fieldname can be changed so that cascade delete is not enabled in Zend_Db_Table_Abstract. FYI: The class names should be the same as the table names.

The input and output fieldsets are structured thus:
(A recordset would consist of an array of multiple fieldsets)

Array
(
	[field1] => value1
	[field2] => value2
	[relatedTableName] => Array
			(
				[0] => Array
					(
						[record1field1] => record1value1
						[record1field2] => record1value2
					)
				[1] => Array
					(
						[record2field1] => record2value1
					)
			)
	[relatedTableName2] => Array
		(
			[0] => Array
				(
					[relatedTableName3] => Array
						(
							[record1field1] => record1value1
						)
				)
		)
)


This class does not delete any data. It only inserts or updates rowsets. 

Data requirements: No reference loops are made with the foreign keys. IE, two tables can't have foreign keys
pointing at each other. Also, there can only be one reference array in _referenceMap fields for each table. This is because the Zend_Db_Table->getReference only returns one reference for the table passed into it. However, there can be multiple fields for each reference.

For input datasets, foreign key fields in the related tables do not need to appear in the related data sets - they will be
grabbed from the parent data set when appropriate. 

Also, unique keys that have a unique index can be used instead of the primary keys, and if the row already exists, 
the primary key will be pulled from the db. Otherwise a new row will be created.

This also works with auto-increment primary keys.

*************

To retrieve data for a table named Title and its related tables, first create a class for each table and then:

$titleObj = new Title();
$titleObj->setFields($title_fields);  //$title_fields is assoc array of fields to use in the where clause
$data = $titleObj->getRecords();


To set data:

$titleObj = new Title();
$titleObj->saveRecords($data);

*************

CALLBACKS

protected function presave_callback()
protected function postsave_callback()

If these functions are declared by the child class, then they will be called before and/or after saving a record.

*/



abstract class Db_Composite extends Zend_Db_Table_Abstract {


	protected $_global_fields;			// just a set of fields to pass down to child relationships - but these fields
										// aren't saved to the database
    
    protected $_parent_fields;			// assoc array to hold the parent fields that will be used as foreign keys
    									// in the child tables
    protected $_parent_relationships;	// list of parent relationships that appear in the data set - this is parent
    									// relative to the current class
    protected $_child_relationships;	// list of child relationships to the current class appearing in the data set
    protected $_fields;					// fieldset for the current record
    protected $_sort_fields;			// array of sort fields - the key is the name of the field, the value is either
    									// ASC or DESC
    protected $_loaded = false;			// flag is the current fieldset has already been loaded from the db or not
    protected $_dependentTablesName = "_dependentTables";	
    									// can be used to change the name of the _dependentTables variable being used
    protected $_calling_name;			// hold the name of the calling table when retrieving related data
    									// so we don't recurse back to the calling table again
    									
    protected $_field_mappings;			// maps property names to db column names - this should be set by the class
    									// it only needs to include mappings that change the names
    									// also, the property names and column names should be mutually exclusive
    									// i.e. there shouldn't be column names that are also db names
    protected $_field_mappings_reverse;	// maps db column names to property names - will be derived from _field_mappings
    
    
public function __construct($do_create=false, $dependentTables=null) {
	logit("creating ".$this->_name);
	if (is_array($dependentTables)) {
		$this->{$this->_dependentTablesName} = $dependentTables;
	}
	
	parent::__construct();
}
 
 
/* These fields will be applied to all of the records
	They are passed from the parent class to the child class in the Save function
*/
public function setParentFields($parent_fields) {
	//logit("setting parent fields");
	//logit($parent_fields);
	
	$parent_fields = $this->_map_fields($parent_fields);

	$this->_parent_fields = $parent_fields;
}


/* Calls setFields and Save on each record
*/
public function saveRecords($records, $global_fields=null) {
	//logit("saving records");
	//logit($records);
	
	
	/*if ($global_fields) {
		echo "these are the global fields";
		print_r($global_fields);
	}*/

	foreach ($records as $record) {
		//print_r($record);
		
		$this->_fields = array();
		$this->setFields($record, $global_fields);
		
		// call pre-save callback
		if (method_exists($this, "presave_callback")) {
			$this->presave_callback();
		}
		$this->save();
	
		if (method_exists($this, "postsave_callback")) {
			$this->postsave_callback();
		}
	}
}



/* Separates the fields out into table fields and parent/child related tables/fields
*/
public function setFields($fields, $global_fields=null) {

	if ($global_fields) {
		$this->_global_fields = $global_fields;
	}

	$fields = $this->_map_fields($fields);

	// first add any fields passed in from the calling class
	$this->_loaded = false;
	if ($this->_parent_fields) {
		$fields = array_merge($fields, $this->_parent_fields);
	}
	
	// sort the fields into fields and related tables
	foreach ($fields as $field=>$value) {
	
		// if the value is an array, then it is a related table and the field name
		// is the name of the table
		if (is_array($value)) {
			try {
				$this->getReference($field);
			} catch (Exception $e) {
				// if the table doesn't appear in the reference list, then this
				// current table is the parent table
				$this->_child_relationships[$field] = $value;
				continue;
			}
			// otherwise this table is the child table
			$this->_parent_relationships[$field] = $value;
			
		} else {
			// otherwise, this isn't a related table - 
			// just add the field to the table's field list
			$this->_fields[$field] = $value;
		}
	}
}


public function save() {
	
	// process the parent relationships first because we will need their data for
	// this object's foreign keys
	foreach ($this->_parent_relationships as $object_name=>$record) {
	
		// we're going to assume each parent relationship is NOT a many-to-many, so the parent
		// object will only have one record, not multiple as the child objects below it 
		
		//logit("saving parent: $object_name");
		
		// so just save the record and pull the referencing keys from the parent fields
		$parent_object = new $object_name();
		$parent_object->saveRecords(array($record), $this->_global_fields);
		$parent_fields = $parent_object->getFields();
		//print_r($parent_fields);
		
		// now get the fields that this table is referencing
		$reference = $this->getReference($object_name);
		$reference = $this->_massageReference($reference);
		//print_r($reference);

		for ($x=0; $x<sizeof($reference['columns']); $x++) {
			$column = $reference['columns'][$x];
			$refColumn = $reference['refColumns'][$x];
			if (! isset($parent_fields[$refColumn])) {
				die("Missing parent field $column for child table " . $this->_name);
			} else {
				$this->_fields[$column] = $parent_fields[$refColumn];
			}
		}
	}
	
	// now process the fields and save to db
	$fields_sql = '';
	$values_sql = '';
	$update_sql = '';
	foreach ($this->_fields as $field=>$value) {
		// create the where statement for the replace query for this table
		
		$fields_sql .= "$field, ";
		//if (is_nan($value)) {
		//	$value = $this->_db->quote($value);
		//}
		$value = $this->_db->quote($value);
		$values_sql .= "$value, ";
		$update_sql .= "$field=$value, ";
		//$values_sql .= "'$value', ";
		//$update_sql .= "$field='$value', ";
	}
	$fields_sql = rtrim($fields_sql, ", ");
	$values_sql = rtrim($values_sql, ", ");
	$update_sql = rtrim($update_sql, ", ");
	
	// create the replace query - this will create a new row if the record doesn't
	// already exist, or it will delete the record and recreate it
	//$sql = "REPLACE INTO " . $this->_name . " ($fields_sql) values ($values_sql)";
	
	// OR, do an ON DUPLICATE KEY query - this will only replace fields that are passed in
	$sql = "INSERT INTO " . $this->_name . " ($fields_sql) values ($values_sql) ON DUPLICATE KEY UPDATE $update_sql";
	logit($sql);
	//echo "<p>$sql</p>";
	
	$rowCount = $this->_db->getConnection()->exec($sql);
	//$results = $this->_db->query($sql);
	// we can't _db->query($query) because that craps out on large queries for some reason
	// I'm not sure if this returns rowCount exactly, but it returns a 0 if it doesn't insert or update anything
	
	// if this has an auto-gen key, get the key
	if (! $this->_fields[$this->_primary] && $this->_sequence === TRUE) {
		//if ($results->rowCount()) {
		if ($rowCount) {
			$this->_fields[$this->_primary] = $this->_db->lastInsertId(); 
		}
	}
	
	// now, process the child relationships
	// the value will be an array of records
	foreach ($this->_child_relationships as $object_name=>$records) {
	
		//logit("saving child: $object_name");
	
		$child_object = new $object_name();
		try {
			$reference = $child_object->getReference($this->_name);
			
		} catch (Exception $e) {
			// there's no reference listing in the child table, so there aren't any
			// foreign keys that need to be passed in as parent values, so just save the record
			//echo "exception";
			$child_object->saveRecords(array($record), $this->_global_fields);
			continue;
		}
		
		//echo "no exception";
		// there are dependent fields - so for each foreign key, add the parent value
		$parent_fields = array();
		$reference = $this->_massageReference($reference);
		//print_r($reference);
		
		for ($x=0; $x<sizeof($reference['columns']); $x++) {
			$column = $reference['columns'][$x];
			$refColumn = $reference['refColumns'][$x];
			if (! isset($this->_fields[$refColumn])) {
				die("Missing parent field $column for child table $object");
			} else {
				$parent_fields[$column] = $this->_fields[$refColumn];
			}
			
		}
		//print_r($parent_fields);
		//echo "asdfasdf";
		$child_object->setParentFields($parent_fields);
		$child_object->saveRecords($records, $this->_global_fields);
	}
	
	//echo memory_get_usage() . "<br />";
}



public function getFields($cascade=false) {
	
	// we're going to assume that the fields already set are the primary key or a unique key
	// so if there are any extra fields, they'll just be added to the where clause - possibly 
	// creating unecessary overhead?
	
	// if the set fields return more than one row, then only the first row is used
	
	// if cascade is true, get data for the related tables as well, 
	// otherwise just get return the table's fields
	
	$result = $this->_getData(false, $cascade);
	//print_r($result);
	$result = $this->_map_fields($result, true);
	
	//print_r($result);
	/*if (! $cascade) {
		return $this->_fields;
	} else {
		return $result;
	}*/
	if ($cascade) {
		return $result[0];
	} else {
		return $result;
	}
}


public function getRecords($cascade=false, $calling_name=null) {

	// same as getFields, but a list of records will be returns, not just one
	$this->_calling_name = $calling_name;
	$result = $this->_getData(true, $cascade);
	$result = $this->_map_records_fields($result, true);

	return $result;
}



protected function _getData($multi=false, $cascade=false) {
	//print_r($this->_fields);
	
	// create the select statement
	$select = $this->select();
	
	// if _records are set instead of fields, then create an OR'd list of
	// records fields
	if ($this->_records) {
		foreach ($this->_records as $fields) {
			$wheres = array();
			foreach ($fields as $field=>$value) {
				$wheres[] = "$field = '$value'";
			}
			$select->orWhere(implode(" AND ", $wheres));
		}
		
	} else {
		foreach ($this->_fields as $field=>$value) {
			$select->where("$field = ?", $value);
		}
	}
	
	if (is_array($this->_sort_fields)) {
		$select->order($this->_sort_fields);
	}
	
	
	logit($select->__toString());
	
	// get either a row or rows
	if ($multi) {
		$records = $this->fetchAll($select)->toArray();
	} else {
		if (! $this->_loaded) {
			//print_r($select->query());
		
			$result = $this->fetchRow($select);
			if (is_object($result)) {
				$records = $result->toArray();
			} else {
				return;
			}
			$this->_fields = $records;
			$this->_loaded = true;
		} else {
			$records = $this->_fields;
		}
	}
	
	//logit("table's data");
	//print_r($records);
	if (sizeof($records) == 0) {
		return;
	}
	
	// if cascade, get the fields for the related tables
	if ($cascade) {
		
		// for the related tables, we have to process each row separately
		if (! $multi) {
			$records = array($records);
		}
	
		if (isset($this->{$this->_dependentTablesName})) {
			foreach ($this->{$this->_dependentTablesName} as $child_name) {
				if ($child_name != $this->_calling_name) {
					$child = new $child_name();
					
					// find out the child's foreign key in this object and pass it in
					try {
						$reference = $child->getReference($this->_name);
					} catch (Exception $e) {
						// if there's no reference in the child table, then something's wrong
						die("no reference in child table $child_name");
						continue;
					}
					
					for ($x=0; $x<sizeof($records); $x++) {
						$this->_fields = $records[$x];
						$records[$x][$child_name] = $this->_getRelationRecords($child_name, $reference);
					}
				}
			}
		}
		
		if (isset($this->_referenceMap)) {
			foreach ($this->_referenceMap as $parent_name=>$reference) {
				if ($parent_name != $this->_calling_name) {
				
					/*for ($x=0; $x<sizeof($records); $x++) {
						$this->_fields = $records[$x];
						$records[$x][$parent_name] = $this->_getRelationRecords($parent_name, $reference);
					}*/
					$this->_records = $records;
					$records = $this->_getRelationRecordsMulti($parent_name, $reference);
				}
			}
		}
	}
			
	return $records;
}


// note - since this is use for both relationship directions, child and parent above, the
// column/refColumn code might be wrong for children or parents - currently this is being used on 
// data that has the same column names so it's not a problem
protected function _getRelationRecords($related_table_name, $reference) {
	//logit("create related table");
	$related_table = new $related_table_name();
	$reference = $this->_massageReference($reference);
		
	for ($x=0; $x<sizeof($reference['columns']); $x++) {
		$column = $reference['columns'][$x];
		$refColumn = $reference['refColumns'][$x];
		$fields[$column] = $this->_fields[$refColumn];
	}
	//logit($fields);
	$related_table->setFields($fields);
	$result = $related_table->getRecords(true, $this->_name);
	return $result;
}



// used to get xref records in one db call
protected function _getRelationRecordsMulti($related_table_name, $reference) {
	//logit("calling relation records multi");
	//print_r($this->_records);
	$related_table = new $related_table_name();
	$reference = $this->_massageReference($reference);
		
	$send_records = array();
	for ($x=0; $x<sizeof($this->_records); $x++) {
		for ($y=0; $y<sizeof($reference['columns']); $y++) {
			$column = $reference['columns'][$y];
			$refColumn = $reference['refColumns'][$y];
			//logit("send_records[$x] = ".$this->_records[$x][$refColumn]);
			$send_records[$x][$column] = $this->_records[$x][$refColumn];
			//print_r($send_records);
		}
	}
	//print_r($send_records);
	$related_table->setRecords($send_records);
	$result = $related_table->getRecords(true, $this->_name);
	
	// now make into the correct format
	$return_result = array();
	foreach ($result as $values) {
		$return_result[] = array($related_table_name => array($values));
	}
	
	return $return_result;
}



// currently this is only used for getting records - the set records will be
// ORd together
public function setRecords($records) {
	$this->_records = $records;
}



// make sure the columns and refColumns properties are arrays
protected function _massageReference($reference) {

	if (! is_array($reference['columns'])) {
		$reference['columns'] = array($reference['columns']);
	}
	if (! is_array($reference['refColumns'])) {
		$reference['refColumns'] = array($reference['refColumns']);
	}
	return $reference;
}



protected function _map_fields($fields, $reverse=false) {

	if ($reverse) {
		if (! isset($this->_field_mappings_reverse)) {
			$this->_field_mappings_reverse = array_flip($this->_field_mappings);
		}
		$mappings = $this->_field_mappings_reverse;
	} else {
		$mappings = $this->_field_mappings;
	}

	foreach ($fields as $field=>$value) {
		if (isset($mappings[$field])) {
			unset($fields[$field]);
			$fields[$mappings[$field]] = $value;
		}
	}
	return $fields;
}



protected function _map_records_fields($records, $reverse=false) {
	
	for ($x=0; $x<sizeof($records); $x++) {
		$records[$x] = $this->_map_fields($records[$x], $reverse);
	}
	return $records;
}


}


		
	
		

?>