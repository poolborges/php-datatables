php-datatables
==============

This is a php library which wraps around the DataTables (http://www.datatables.net) plugin for jQuery.

This library provides a object-oriented interface to configure and build javascript data tables. The goal
of this library is to provide an easily extendable framework that allows DataTables to be easily incorporated
into any existing application or web framework. The library provides the means to build DataTables that can 
render data from any source including databases, web services, csv files, etc...

php-datatables doesn't care how or where the data for the table is obtained. The only assumption is that
your data is provided as an array of entity objects which each have standard getter methods.

## Overview

## Class Overview

### DataTable_DataTable

This class is the heart of the library. You simply extend this class for each DataTable that you will need
in your application. The purpose of this class is to obtain the data for the table and to format the results
that will be output.

Here are the basic requirements.

1. Pass in a DataTable_Config object (see below).

   Your DataTable will need a config object in order define the columns and various options. You can either
   pass this object into the constructor or define it within your constructor and pass it on to the parent
   class.

2. Implement the loadData() method

   This method is where you pull the data you want displayed and return it within a DataTable_DataResult object.
   You are provided with a DataTable_Request object which allows you to pull the pagination information that
   is passed in through AJAX requests.   

3. Implement the getTableId() method

   Simply return a unique id for this table instance. This id is used ass the html id attribute in the rendered
   html table.

4. Implement getter methods to format colummn values

   In each DataTable_Column object (see below) you must define a getter method name so that the column knows
   where to obtain the value for each entity object. By default, DataTable_DataTable will call that getter
   method on the entity object. However, you can implement the getter method within your DataTable class
   in order to format the column values however you want.

   The only requirement is that your method should expect to receive an entity object as it's only parameter.

   protected function getFullName(User $user)
   {
     return $user->getFirstName() . ' ' . $user->getLastName();
   }

5. Implement javascript callback functions

   If your datatable needs to implement the various DataTable callback methods (http://datatables.net/usage/callbacks)
   simply implement the various methods found within DataTable_DataTable.

   protected function getRowCallbackFunction()
   { 
     return "
		function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
			/* Bold the grade for all 'A' grade browsers */
			if ( aData[4] == 'A' )
			{
				$('td:eq(4)', nRow).html( '<b>A</b>' );
			}
			return nRow;
		}

     ";
   }


### DataTable_Config

This class defines all the configuration options for a DataTable. A DataTable_DataTable object expects to receive
a DataTable_Config object. This object set's all the various options that get passed to the javascript table. This
object also holds a collection of all the DataTable_Column definitions.

### DataTable_Column

This class defines all the options for an individual column within the DataTable. Each column must at the very least
have a name, title, and getter method name. You can configure whether columns are sortable, searchable, etc.

    1. setGetMethod()

    This method let's DataTable_DataTable know where it should obtain the value for the current column. First, it
    will check to see if this method exists within your DataTable_DataTable implementing class. Otherwise, it
    will call the getter method on the entity object. 

### DataTable_Request

An object of this class needs to be passed into the DataTable_DataTable->renderJson() method. This eventually gets
passed into your loadData() implementation. This object simply stores the parameters that are passed from DataTables
within AJAX requests and allow you to access them for pagination, sorting, and searching.

By default, this class provides a hydration method to fill the object with the parameters from a $_GET, $_POST, or
$_REQUEST array.

$request = new DataTable_Request();
$request->fromPhpRequest($_REQUEST);

You can extend this class to hydrate the parameters from some other framework specific request object if you need to.

### DataTable_DataResult

This is the type of object that is expected to be returned from your DataTable_DataTable->loadData() implementation.
You just need to pass in the array of your entities and a count of the total number of results for pagination (total
records for all pages).