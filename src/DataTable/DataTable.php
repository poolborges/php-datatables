<?php

/**
 * This file is part of the DataTable package
 * 
 * (c) Marc Roulias <marc@lampjunkie.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DataTable;

/**
 * This is the base class that all DataTables need to extend
 * 
 */
abstract class DataTable
{
  /**
   * The DataTable\Config object
   * @var DataTable\Config
   */
  protected $config;
  
  /**
   * The Ajax source url
   * 
   * @var string
   */
  protected $ajaxDataUrl;
  
  /**
   * The server parameters passed in the AJAX request
   * 
   * @var DataTable\Request
   */
  protected $request;

  /**
   * Array to store javascript callback functions each
   * with a unique key
   * 
   * @var array
   */
  protected $jsonFunctions;
  
  /**
   * Array to store mapping of column name => position index
   *
   * @var array
   */
  protected $columnIndexNameCache;

  /**
   * Creates a new DataTable using the given DataTable\Config object
   * 
   * @param DataTable\Config $config
   */
  public function __construct(Config $config = null)
  {
    if(is_null($config)){
      throw new DataTableException("A DataTable\Config object is required.");
    }

    $this->config = $config;
  }

  /**
   * Get a unique id for the current DataTable
   * 
   * This value is used as the HTML id on the table when it
   * is rendered in the HTML ouput
   * 
   * @return string
   */
  abstract public function getTableId();
  
  /**
   * Load data for an AJAX request
   * 
   * This method must return a DataTable\DataResult object
   * 
   * @param DataTable\ServerParameterHolder $parameters
   * @return DataTable\DataResult
   */
  abstract protected function loadData(Request $request);

  /**
   * Override this method to return the javascript function that
   * will be passed as the 'rowCallback' option
   * 
   * @return string
   */
  protected function getRowCallbackFunction(){}

  /**
   * Override this method to return the javascript function that
   * will be passed as the 'fnInitComplete' option
   * 
   * @return string
   */
  protected function getInitCompleteFunction(){}
  
  /**
   * Override this method to return the javascript function that
   * will be passed as the 'fnDrawCallback' option
   * 
   * @return string
   */
  protected function getDrawCallbackFunction(){}
  
  /**
   * Override this method to return the javascript function that
   * will be passed as the 'fnFooterCallback' option
   * 
   * @return string
   */
  protected function getFooterCallbackFunction(){}
  
  /**
   * Override this method to return the javascript function that
   * will be passed as the 'fnHeaderCallback' option
   * 
   * @return string
   */
  protected function getHeaderCallbackFunction(){}
  
  /**
   * Override this method to return the javascript function that
   * will be passed as the 'fnInfoCallback' option
   * 
   * @return string
   */
  protected function getInfoCallbackFunction(){}
  
  /**
   * Render the initial HTML and javascript to instantiate and display the DataTable
   * 
   * @return string
   */
  public function render()
  {
    if(is_null($this->config)){
      throw new DataTableException("A DataTable\Config object is required.");
    }

    return $this->renderHtml() . $this->renderJs();
  }
  
  /**
   * Get the JSON formatted date for a AJAX request
   * 
   * @param DataTable\ServerParameterHolder $serverParameters
   * @return string
   */
  public function renderJson(Request $request)
  {
    if(is_null($this->config)){
      throw new DataTableException("A DataTable\Config object is required.");
    }

    $this->request = $request;
    $dataTableDataResult = $this->loadData($request);
    return $this->renderReturnData($dataTableDataResult);
  }

  /**
   * Render the return JSON data for the AJAX request with the DataTable\DataResult
   * returned from the current DataTable's loadData() method
   * 
   * @param DataTable\DataResult $result
   */
  protected function renderReturnData(DataResult $result)
  {
    $rows = array();

    foreach($result->getData() as $object){

      $row = array();

      foreach($this->config->getColumns() as $column){
        $row[] = $this->getDataForColumn($object, $column);
      }

      $rows[] = $row;
    }

    $data = array(
			'iTotalRecords' => $result->getNumTotalResults(),
			'iTotalDisplayRecords' => !is_null($result->getNumFilteredResults()) ? 
                                        $result->getNumFilteredResults() : $result->getNumTotalResults(),
			'data' => $rows,
			'sEcho' => $this->request->getEcho(),		
    );

    return json_encode($data);
  }

  /**
   * Get the data for for a column from the given data object row
   * 
   * This method will first try calling the get method on the current
   * DataTable object. If the method doesn't exist, then it will default
   * to calling the method on the object for the current row
   * 
   * @param object $object
   * @param DataTable\Column $column
   * @return mixed
   */
  protected function getDataForColumn($object, Column $column)
  {
    $getter = $column->getGetMethod();

    if(method_exists($this, $getter)){
      return call_user_func(array($this, $getter), $object);  
    } else {
    
      if(method_exists($object, $getter)){
        return call_user_func(array($object, $getter));
      } else {
        throw new DataTableException("$getter() method is required in " . get_class($object) . " or " . get_class($this));
      }
    }
  }

  /**
   * Render the default table HTML
   * 
   * @return string
   */
  protected function renderHtml()
  {
    $html = '';
    $html .= "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class=\"{$this->config->getClass()}\" id=\"{$this->getTableId()}\">";
    $html .= "<thead><tr>";

    foreach($this->config->getColumns() as $column){
      if($column->isVisible()){
        $html .= "<th>{$column->getTitle()}</th>";
	  } else {
        $html .= "<th style=\"display: none;\">{$column->getTitle()}</th>";
	  }    
    }

    $html .= "</tr></thead>";
    $html .= "<tbody>";

    if(!$this->config->isServerSideEnabled()){
      
      $html .= $this->renderStaticData();
      
    } else {
    
      $html .= "<tr><td class=\"dataTables_empty\">{$this->config->getLoadingHtml()}</td>";
    }
    
    $html .= "</tbody>";
    $html .= "</table>";

    $html .= "<!-- Built with LampJunkie php-datatables -->";
    
    return $html;
  }

  /**
   * Render the table rows for a non-ajax datatable
   * 
   * @return string
   */
  protected function renderStaticData()
  {
    $data = $this->loadStaticData();

    $html = "";
    
    foreach($data as $object){

      $row = "";

      foreach($this->config->getColumns() as $column){
        $value = $this->getDataForColumn($object, $column);
        
        if($column->isVisible()){
          $row .= "<td>{$value}</td>";
        } else {
          $row .= "<td style=\"display: none;\">{$value}</td>";
        }
      }

      $html .= "<tr>{$row}</tr>";
    }
    
    return $html;
  }

  /**
   * Call the implementing loadData() method to load static data
   * for a non-AJAX table
   * 
   * @return array
   */
  protected function loadStaticData()
  {
    // find the default sort column and direction from the config
    foreach($this->config->getColumns() as $index => $column){
      if($column->isDefaultSort()){
        $sortColumnIndex = $index;
        $sortDirection = $column->getDefaultSortDirection();
      }      
    }

    // make a fake request object
    $request = new Request();
    $request->setDisplayStart(0);
    $request->setDisplayLength($this->config->getStaticMaxLength());
    $request->setSortColumnIndex($sortColumnIndex);
    $request->setSortDirection($sortDirection);

    // load data
    $dataResult = $this->loadData($request);

    // just return the entity array
    return $dataResult->getData();
  }
 
  /**
   * Render the DataTable instantiation javascript code
   * 
   * @return
   */
  protected function renderJs()
  {
    $js = "
			<script type=\"text/javascript\">
			    $(document).ready(function(){
					var {$this->getTableId()} = $('#{$this->getTableId()}').DataTable({$this->renderDataTableOptions()});
			    });
			</script>
		";

    return $js;
  }

  /**
   * Convert all the DataTable\Config options into a javascript array string
   * 
   * @return string
   */
  protected function renderDataTableOptions()
  {
    $options = array();

    $options["paging"]          = $this->config->isPaginationEnabled();
    $options["lengthChange"] 	= $this->config->isLengthChangeEnabled();
    $options["processing"] 	= $this->config->isProcessingEnabled();
    $options["searching"]       = $this->config->isSearchingEnabled();
    $options["ordering"] 	= $this->config->isOrderingEnabled();
    $options["info"] 	      	= $this->config->isInfoEnabled();
    $options["autoWidth"]       = $this->config->isAutoWidthEnabled();
    $options["scrollCollapse"]	= $this->config->isScrollCollapseEnabled();
    $options["pageLength"] 	= $this->config->getPageLength();
    $options["jQueryUI"]        = $this->config->isJQueryUIEnabled();
    $options["pagingType"]	= $this->config->getPagingType();    

    $options["stateSave"]       = $this->config->isSaveStateEnabled();
    $options["stateDuration"]   = $this->config->getStateDuration();
    
    $options["columns"]         = $this->renderDataTableColumnOptions();
    $options["order"]     	= $this->renderDefaultOrderColumns();
    $options["lengthMenu"] 	= $this->renderLengthMenu();
    
    if($this->config->isServerSideEnabled()){
      $options["serverSide"] 	= $this->config->isServerSideEnabled();
      $options["sAjaxSource"] 	= $this->getAjaxSource();       //TODO - check why if change this to ajax, get ERROR
    }  
    
    if(!is_null($this->config->getScrollX())){
      $options["scrollX"] = $this->config->getScrollX();
    }

    if(!is_null($this->config->getScrollY())){
      $options["scrollY"] = $this->config->getScrollY();
    }
    
    if(!is_null($this->config->getLanguageConfig())){
      $options["language"]	= $this->renderLanguageConfig();
    }
    
    if(!is_null($this->config->getDom())){
      $options["dom"] = $this->config->getDom();
    }
    
    // =====================================================================================
    // add callback functions
    // =====================================================================================
    if(!is_null($this->getRowCallbackFunction())){
      $options["rowCallback"] = $this->getCallbackFunctionProxy('getRowCallbackFunction');
    }

    if(!is_null($this->getInitCompleteFunction())){
      $options["initComplete"] = $this->getCallbackFunctionProxy('getInitCompleteFunction');
    } 
    
    if(!is_null($this->getDrawCallbackFunction())){
      $options["drawCallback"] = $this->getCallbackFunctionProxy('getDrawCallbackFunction');
    } 
    
    if(!is_null($this->getFooterCallbackFunction())){
      $options["footerCallback"] = $this->getCallbackFunctionProxy('getFooterCallbackFunction');
    } 
    
    if(!is_null($this->getFooterCallbackFunction())){
      $options["headerCallback"] = $this->getCallbackFunctionProxy('getHeaderCallbackFunction');
    }  
    
    if(!is_null($this->getInfoCallbackFunction())){
      $options["infoCallback"] = $this->getCallbackFunctionProxy('getInfoCallbackFunction');
    } 
    
    // build the initial json object
    $json = json_encode($options);
    
    // replace keys for functions with actual functions
    $json = $this->replaceJsonFunctions($json);
    
    return $json;    
  }

  /**
   * This method replaces any keys within the given json string 
   * that were created in getCallbackFunctionProxy
   * 
   * This essentially is a hack to make sure that the functions
   * don't have double quotes around them which keeps javascript
   * from interpreting them as functions.
   * 
   * @param string $json
   */
  protected function replaceJsonFunctions($json)
  {
    if(!is_null($this->jsonFunctions)){
      foreach($this->jsonFunctions as $key => $function){
      
        $search = '"' . $key . '"';
      
        $json = str_replace($search, $function, $json);
      }
    }
    
    return $json;
  }
  
  /**
   * Proxy method to call the current object's getRowCallBackFunction() method
   * and clean up the result for the javascript.
   * 
   * This method also creates a unique key for each function which it returns
   * for later lookup against the function stored in $this->jsonFunctions
   * 
   * @return string
   */
  protected function getCallbackFunctionProxy($function)
  {
    // get the js function string
    $js = call_user_func(array($this, $function));

    $jsonKey = $this->buildJsonFunctionKey($js);
    
    return $jsonKey;
  }


  /**
   * Build a unique key for the given javascript function
   * and store they key => function in the local jsonFunctions
   * variable.
   * 
   * This key will get used later in replaceFunctions to replace
   * the key with the actual function to fix the final json string.
   * 
   * @return string
   */ 
  protected function buildJsonFunctionKey($js)
  {
    // remove comments
    $js = preg_replace('!/\*.*?\*/!s', '', $js);  // removes /* comments */
    $js = preg_replace('!//.*?\n!', '', $js); // removes //comments
   
    // remove all extra whitespace
    $js = str_replace(array("\t", "\n", "\r\n"), '', trim($js));
     
    // build a temporary key
    $jsonKey = md5($js);
    
    // store key => function mapping
    $this->jsonFunctions[$jsonKey] = $js;
    
    return $jsonKey;
  }
  
  /**
   * Build the array for the 'columns' DataTable option
   * 
   * @return array
   */
  protected function renderDataTableColumnOptions()
  {
    $columns = array();

    foreach($this->config->getColumns() as $column){

      $tempColumn = array(
				"orderable" => $column->isSortable(),
				"name" => $column->getName(),
				"visible" => $column->isVisible(),
                "searchable" => $column->isSearchable(),
      );

      if(!is_null($column->getWidth())){
        $tempColumn['width'] = $column->getWidth();
      }

      if(!is_null($column->getClass())){
        $tempColumn['className'] = $column->getClass();
      }

      if(!is_null($column->getRenderFunction())){
        $tempColumn['render'] = $this->buildJsonFunctionKey($column->getRenderFunction());
      }
      
      $columns[] = $tempColumn;
    }

    return $columns;
  }

  /**
   * Build the array for the 'order' option
   * 
   * @return array
   */
  protected function renderDefaultOrderColumns()
  {
    $columns = array();

    foreach($this->config->getColumns() as $id => $column){
      if($column->isDefaultSort()){
        $columns[] = array($id, $column->getDefaultSortDirection());
      }
    }

    return $columns;
  }

  /**
   * Build the array for the 'aLengthMenu' option
   * 
   * @return array
   */
  protected function renderLengthMenu()
  {
    return array(array_keys($this->config->getLengthMenu()), array_values($this->config->getLengthMenu()));
  }

  /**
   * Build the array for the 'oLanguage' option from the LanguageConfig object
   * 
   * @return array
   */
  protected function renderLanguageConfig()
  {
    $options = array();

    $paginate = array();

    if(!is_null($this->config->getLanguageConfig()->getPaginateFirst())){
	  $paginate["first"] = $this->config->getLanguageConfig()->getPaginateFirst();
    }

    if(!is_null($this->config->getLanguageConfig()->getPaginateLast())){
	  $paginate["last"] = $this->config->getLanguageConfig()->getPaginateLast();
    }

    if(!is_null($this->config->getLanguageConfig()->getPaginateNext())){
	  $paginate["next"] = $this->config->getLanguageConfig()->getPaginateNext();
    }

    if(!is_null($this->config->getLanguageConfig()->getPaginatePrevious())){
	  $paginate["previous"] = $this->config->getLanguageConfig()->getPaginatePrevious();
    }

    // add oPaginate to options if anything was set for object
    if(count($paginate) > 0){
      $options["paginate"] = $paginate;
    }

    if(!is_null($this->config->getLanguageConfig()->getEmptyTable())){
	  $options["emptyTable"] = $this->config->getLanguageConfig()->getEmptyTable();
    }
    
    if(!is_null($this->config->getLanguageConfig()->getInfo())){
	  $options["info"] = $this->config->getLanguageConfig()->getInfo();
    }
      
    if(!is_null($this->config->getLanguageConfig()->getInfoEmpty())){
	  $options["infoEmpty"] = $this->config->getLanguageConfig()->getInfoEmpty();
    }
      
    if(!is_null($this->config->getLanguageConfig()->getInfoFiltered())){
	  $options["infoFiltered"] = $this->config->getLanguageConfig()->getInfoFiltered();
    }
     
    if(!is_null($this->config->getLanguageConfig()->getInfoPostFix())){
	  $options["infoPostFix"] = $this->config->getLanguageConfig()->getInfoPostFix();
    }
    
    if(!is_null($this->config->getLanguageConfig()->getLengthMenu())){
	  $options["lengthMenu"] = $this->config->getLanguageConfig()->getLengthMenu();
    }
      
    if(!is_null($this->config->getLanguageConfig()->getSearch())){
	  $options["search"] = $this->config->getLanguageConfig()->getSearch();
    }

    if(!is_null($this->config->getLanguageConfig()->getZeroRecords())){
	  $options["zeroRecords"] = $this->config->getLanguageConfig()->getZeroRecords();
    }
    
    if(!is_null($this->config->getLanguageConfig()->getUrl())){
	  $options["url"] = $this->config->getLanguageConfig()->getUrl();
    }

    return $options;
  }

  /**
   * Set the ajax source url for the current object
   * 
   * This overrides the value that may have been set on
   * the DataTable\Config object
   * 
   * @param string $ajaxDataUrl
   */
  public function setAjaxDataUrl($ajaxDataUrl)
  {
    $this->ajaxDataUrl = $ajaxDataUrl;
  }

  /**
   * Get the ajax source url that was set either on the DataTable\Config
   * object or on the current DataTable object
   * 
   * @return string
   */
  public function getAjaxSource()
  {
    if(!is_null($this->config->getAjaxSource())){
      return $this->config->getAjaxSource();
    } else {
      return $this->ajaxDataUrl;
    }
  }

  /**
   * Utility method to find a column positon index
   * by the column's name
   *
   * @return integer
   */
  protected function getColumnIndexByName($name)
  {
    if(is_null($this->columnIndexNameCache)){
      $this->buildColumnIndexNameCache();
    }

    return $this->columnIndexNameCache[$name];
  }

  /**
   * Utility method to get all the column names 
   * that are configured as being searchable
   * 
   * @return array
   */
  protected function getSearchableColumnNames()
  {
    $cols = array();
    
    foreach($this->config->getColumns() as $column){
      if($column->isSearchable()){
        $cols[] = $column->getName();
      }
    }
    
    return $cols;
  }
  
  /**
   * Build an array of Column->name => position index
   * for quick lookups
   *
   * @return void
   */
  protected function buildColumnIndexNameCache()
  {
    $this->columnIndexNameCache = array();

    foreach($this->config->getColumns() as $index => $column){
      $this->columnIndexNameCache[$column->getName()] = $index;
    }
  }
}
