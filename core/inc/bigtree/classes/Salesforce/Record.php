<?php
	/*
		Class: BigTree\Salesforce\Record
			A Salesforce object that contains information about and methods you can perform on a record.
	*/

	namespace BigTree\Salesforce;

	class Record {

		/** @var \BigTree\Salesforce\API */
		protected $API;

		public $Columns;
		public $CreatedAt;
		public $CreatedBy;
		public $ID;
		public $Type;
		public $UpdatedAt;
		public $UpdatedBy;

		/*
			Constructor:
				Creates a new BigTree\Salesforce\Record object.

			Parameters:
				record - Salesforce data
				api - Reference to BigTree\Salesforce\API class instance
		*/

		function __construct($record,&$api) {
			$this->API = $api;
			
			// Save this ahead of time to keep things alphabetized.
			$this->Columns = $record;
			$this->CreatedAt = date("Y-m-d H:i:s",strtotime($record->CreatedDate));
			$this->CreatedBy = $record->CreatedById;
			$this->ID = $record->Id;
			$this->Type = $record->attributes->type;
			$this->UpdatedAt = date("Y-m-d H:i:s",strtotime($record->LastModifiedDate));
			$this->UpdatedBy = $record->LastModifiedById;
			
			// Remove a bunch of columns we can't modify
			unset($record->attributes);
			unset($this->Columns->CreatedById);
			unset($this->Columns->CreatedDate);
			unset($this->Columns->Id);
			unset($this->Columns->JigsawCompanyId);
			unset($this->Columns->LastActivityDate);
			unset($this->Columns->LastModifiedDate);
			unset($this->Columns->LastModifiedById);
			unset($this->Columns->LastReferencedDate);
			unset($this->Columns->LastViewedDate);
			unset($this->Columns->MasterRecordId);
			unset($this->Columns->SystemModstamp);
		}

		/*
			Function: delete
				Deletes the record from Salesforce.

			Returns:
				true if successful.
		*/

		function delete() {
			$response = $this->API->callUncached("sobjects/".$this->Type."/".$this->ID,false,"DELETE");

			// If we have a response, there's an error.
			if ($response) {
				$this->API->Errors[] = json_decode($response);
				return false;
			}

			return true;
		}

		/*
			Function: save
				Saves changes made to the Columns property of this object back to Salesforce.
		*/

		function save() {
			$response = $this->API->callUncached("sobjects/".$this->Type."/".$this->ID,json_encode($this->Columns),"PATCH");

			// If we have a response, there's an error.
			if ($response) {
				$this->API->Errors[] = json_decode($response);
				return false;
			}

			return true;
		}

		/*
			Function: update
				Updates this entry in Salesforce.

			Parameters:
				fields - Either a single column key or an array of column keys (if you pass an array you must pass an array for values as well)
				values - Either a signle column value or an array of column values (if you pass an array you must pass an array for fields as well)
		*/

		function update($fields,$values) {
			$record = array();
			if (is_array($fields)) {
				foreach ($fields as $key) {
					$record[$key] = current($values);
					next($values);
				}
			} else {
				$record[$fields] = $values;
			}
			$response = $this->API->callUncached("sobjects/".$this->Type."/".$this->ID,json_encode($record),"PATCH");

			// If we have a response, there's an error.
			if ($response) {
				$this->API->Errors[] = json_decode($response);
				return false;
			}

			return true;
		}

	}
