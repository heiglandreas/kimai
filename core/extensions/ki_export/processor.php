<?php
/**
 * This file is part of
 * Kimai - Open Source Time Tracking // http://www.kimai.org
 * (c) 2006-2009 Kimai-Development-Team
 *
 * Kimai is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; Version 3, 29 June 2007
 *
 * Kimai is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Kimai; If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Export Processor.
 */

// insert KSPI
$isCoreProcessor = 0;
$dir_templates = "templates/";
require("../../includes/kspi.php");

require("private_func.php");


// ============================
// = parse general parameters =
// ============================

if ($axAction == 'export_csv'  ||
    $axAction == 'export_pdf'  ||
    $axAction == 'export_pdf2' ||
    $axAction == 'export_html' ||
    $axAction == 'export_xls'  ||
    $axAction == 'reload') {

  if (isset($_REQUEST['axColumns'])) {
    $axColumns = explode('|',$_REQUEST['axColumns']);
    $columns = array();
    foreach ($axColumns as $column)
      $columns[$column] = true;
  }

  $timeformat = strip_tags($_REQUEST['timeformat']);
  $timeformat = preg_replace('/([A-Za-z])/','%$1',$timeformat);

  $dateformat = strip_tags($_REQUEST['dateformat']);
  $dateformat = preg_replace('/([A-Za-z])/','%$1',$dateformat);

  $default_location = strip_tags($_REQUEST['default_location']);

  $reverse_order = isset($_REQUEST['reverse_order']);
  
  $filter_cleared     = $_REQUEST['filter_cleared'];
  $filter_refundable  = $_REQUEST['filter_refundable'];
  $filter_type        = $_REQUEST['filter_type'];
  
  $filters = explode('|',$axValue);

  if ($filters[0] == "")
    $filterUsers = array();
  else
    $filterUsers = explode(':',$filters[0]);

  if ($filters[1] == "")
    $filterCustomers = array();
  else
    $filterCustomers = explode(':',$filters[1]);

  if ($filters[2] == "")
    $filterProjects = array();
  else
    $filterProjects = explode(':',$filters[2]);

  if ($filters[3] == "")
    $filterActivities = array();
  else
    $filterActivities = explode(':',$filters[3]);

  // if no userfilter is set, set it to current user
  if (isset($kga['user']) && count($filterUsers) == 0)
    array_push($filterUsers,$kga['user']['userID']);
    
  if (isset($kga['customer']))
    $filterCustomers = array($kga['customer']['customerID']);
}





// ==================
// = handle request =
// ==================
switch ($axAction) {   
    

    // ======================
    // = set status cleared =
    // ======================
    case 'set_cleared':
      if (isset($kga['customer'])) {
        echo 0;
        break;
      }
      // $axValue: 1 = cleared, 0 = not cleared
      $id = isset($_REQUEST['id']) ? strip_tags($_REQUEST['id']) : null;
      $success = false;

      if (strncmp($id,"timeSheet",9) == 0)
        $success = export_timeSheetEntry_set_cleared(substr($id,9),$axValue==1);
      else if (strncmp($id,"expense",7) == 0)
        $success = export_expense_set_cleared(substr($id,7),$axValue==1);

      echo $success?1:0;
    break;
    

    // =========================
    // = save selected columns =
    // =========================
    case 'toggle_header':
      // $axValue: header name
      $success = export_toggle_header($axValue);
      echo $success?1:0;
    break;

    // ===========================
    // = Load data and return it =
    // ===========================
    case 'reload':
    	$exportData = export_get_data($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities,false,$reverse_order,$default_location,$filter_cleared,$filter_type,false,$filter_refundable);
        $view->exportData = count($exportData)>0?$exportData:0;

        $view->total = Format::formatDuration($database->get_duration($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities,$filter_cleared));

        $ann = export_get_user_annotations($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities);
        Format::formatAnnotations($ann);
        $view->user_annotations = $ann;
        
        $ann = export_get_customer_annotations($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities);
        Format::formatAnnotations($ann);
        $view->customer_annotations = $ann;

        $ann = export_get_project_annotations($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities);
        Format::formatAnnotations($ann);
        $view->project_annotations = $ann;

        $ann = export_get_activity_annotations($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities);
        Format::formatAnnotations($ann);
        $view->activity_annotations = $ann;

        $view->timeformat = $timeformat;
        $view->dateformat = $dateformat;
        if (isset($kga['user']))
          $view->disabled_columns = export_get_disabled_headers($kga['user']['userID']);
        echo $view->render("table.php");
    break;


    /**
     * Exort as html file.
     */
    case 'export_html':   

        $database->user_set_preferences(array(
          'print_summary' => isset($_REQUEST['print_summary'])?1:0,
          'reverse_order' => isset($_REQUEST['reverse_order'])?1:0),
          'ki_export.print.');
          
       
        $exportData = export_get_data($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities,false,$reverse_order,$default_location,$filter_cleared,$filter_type,false,$filter_refundable);
        $timeSum = 0;
        $wageSum = 0;
        $budgetSum = 0;
        $approvedSum = 0;
        foreach ($exportData as $data) {
          $timeSum += $data['decimalDuration'];
          $wageSum += $data['wage'];
          $budgetSum += $data['budget'];
          $approvedSum += $data['approved'];
        }
        
        $view->timespan = strftime($kga['date_format']['2'],$in).' - '.strftime($kga['date_format']['2'],$out) ;

        if (isset($_REQUEST['print_summary'])) {
          //Create the summary. Same as in PDF export
          $timeSheetSummary = array();
          $expenseSummary = array();
          foreach ($exportData as $one_entry) {

            if ($one_entry['type'] == 'timeSheet') {
              if (isset($timeSheetSummary[$one_entry['activityID']])) {
                $timeSheetSummary[$one_entry['activityID']]['time']   += $one_entry['decimalDuration']; //Sekunden
                $timeSheetSummary[$one_entry['activityID']]['wage']   += $one_entry['wage']; //Currency
                $timeSheetSummary[$one_entry['activityID']]['budget'] += $one_entry['budget']; //Currency
                $timeSheetSummary[$one_entry['activityID']]['approved']+= $one_entry['approved']; //Currency
              }
              else {
                $timeSheetSummary[$one_entry['activityID']]['name']         = html_entity_decode($one_entry['activityName']);
                $timeSheetSummary[$one_entry['activityID']]['time']         = $one_entry['decimalDuration'];
                $timeSheetSummary[$one_entry['activityID']]['wage']         = $one_entry['wage'];
                $timeSheetSummary[$one_entry['activityID']]['budget'] 	  = $one_entry['budget']; 
                $timeSheetSummary[$one_entry['activityID']]['approved']	  = $one_entry['approved'];
              }
            }
            else {
              $expenseInfo['name']   = $kga['lang']['export_extension']['expense'].': '.$one_entry['activityName'];
              $expenseInfo['time']   = -1;
              $expenseInfo['wage'] = $one_entry['wage'];
              $expenseInfo['budget'] = null;
              $expenseInfo['approved'] = null;
              
              $expenseSummary[] = $expenseInfo;
            }
          }
          
          $summary = array_merge($timeSheetSummary,$expenseSummary);
          $view->summary = $summary;
        }
        else
          $view->summary = 0;


        // Create filter descirption, Same is in PDF export
        $customers = array();
        foreach ($filterCustomers as $customerID) {
          $customer_info = $database->customer_get_data($customerID);
          $customers[] = $customer_info['name'];
        }
        $view->customersFilter = implode(', ',$customers);

        $projects = array();
        foreach ($filterProjects as $projectID) {
          $project_info = $database->project_get_data($projectID);
          $projects[] = $project_info['name'];
        }
        $view->projectsFilter = implode(', ',$projects);

        $view->exportData = count($exportData)>0?$exportData:0;

        $view->columns = $columns;
        $view->custom_timeformat = $timeformat;
        $view->custom_dateformat = $dateformat;
        $view->timeSum = $timeSum;
        $view->wageSum = $wageSum;
        $view->budgetSum = $budgetSum;
        $view->approvedSum = $approvedSum;

        header("Content-Type: text/html");
        echo $view->render("formats/html.php");
    break;


    /**
     * Exort as excel file.
     */
    case 'export_xls':

        $database->user_set_preferences(array(
          'decimal_separator' => $_REQUEST['decimal_separator'],
          'reverse_order' => isset($_REQUEST['reverse_order'])?1:0),
          'ki_export.xls.');      
       
        $exportData = export_get_data($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities,false,$reverse_order,$default_location,$filter_cleared,$filter_type,false,$filter_refundable);
        for ($i=0;$i<count($exportData);$i++) {
          $exportData[$i]['decimalDuration'] = str_replace(".",$_REQUEST['decimal_separator'],$exportData[$i]['decimalDuration']);
          $exportData[$i]['rate'] = str_replace(".",$_REQUEST['decimal_separator'],$exportData[$i]['rate']);
          $exportData[$i]['wage'] = str_replace(".",$_REQUEST['decimal_separator'],$exportData[$i]['wage']);
        }
        $view->exportData = count($exportData)>0?$exportData:0;

        $view->columns = $columns;
        $view->custom_timeformat = $timeformat;
        $view->custom_dateformat = $dateformat;

        header("Content-Disposition:attachment;filename=export.xls");
        header("Content-Type: application/vnd.ms-excel");
        echo $view->render("formats/excel.php");
    break;


    /**
     * Exort as csv file.
     */
    case 'export_csv':

        $database->user_set_preferences(array(
          'column_delimiter' => $_REQUEST['column_delimiter'],
          'quote_char' => $_REQUEST['quote_char'],
          'reverse_order' => isset($_REQUEST['reverse_order'])?1:0),
          'ki_export.csv.');      
       
        $exportData = export_get_data($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities,false,$reverse_order,$default_location,$filter_cleared,$filter_type,false,$filter_refundable);
        $column_delimiter = $_REQUEST['column_delimiter'];
        $quote_char = $_REQUEST['quote_char'];

        header("Content-Disposition:attachment;filename=export.csv");
        header("Content-Type: text/csv ");

        $row = array();
        
        // output of headers
        if (isset($columns['date']))
          $row[] = csv_prepare_field($kga['lang']['datum'],$column_delimiter,$quote_char);
        if (isset($columns['from']))
          $row[] = csv_prepare_field($kga['lang']['in'],$column_delimiter,$quote_char);            
        if (isset($columns['to']))
          $row[] = csv_prepare_field($kga['lang']['out'],$column_delimiter,$quote_char);           
        if (isset($columns['time']))
          $row[] = csv_prepare_field($kga['lang']['time'],$column_delimiter,$quote_char);          
        if (isset($columns['dec_time']))
          $row[] = csv_prepare_field($kga['lang']['timelabel'],$column_delimiter,$quote_char);     
        if (isset($columns['rate']))
          $row[] = csv_prepare_field($kga['lang']['rate'],$column_delimiter,$quote_char);          
        if (isset($columns['wage']))
          $row[] = csv_prepare_field($kga['currency_name'],$column_delimiter,$quote_char);                      
        if (isset($columns['budget']))
          $row[] = csv_prepare_field($kga['lang']['budget'],$column_delimiter,$quote_char);                      
        if (isset($columns['approved']))
          $row[] = csv_prepare_field($kga['lang']['approved'],$column_delimiter,$quote_char);                      
        if (isset($columns['status']))
          $row[] = csv_prepare_field($kga['lang']['status'],$column_delimiter,$quote_char);                      
        if (isset($columns['billable']))
          $row[] = csv_prepare_field($kga['lang']['billable'],$column_delimiter,$quote_char);                      
        if (isset($columns['customer']))
          $row[] = csv_prepare_field($kga['lang']['customer'],$column_delimiter,$quote_char);           
        if (isset($columns['project']))
          $row[] = csv_prepare_field($kga['lang']['project'],$column_delimiter,$quote_char);           
        if (isset($columns['activity']))
          $row[] = csv_prepare_field($kga['lang']['activity'],$column_delimiter,$quote_char);           
        if (isset($columns['comment']))
          $row[] = csv_prepare_field($kga['lang']['comment'],$column_delimiter,$quote_char);       
        if (isset($columns['location']))
          $row[] = csv_prepare_field($kga['lang']['location'],$column_delimiter,$quote_char);      
        if (isset($columns['trackingNumber']))
          $row[] = csv_prepare_field($kga['lang']['trackingNumber'],$column_delimiter,$quote_char);    
        if (isset($columns['user']))
          $row[] = csv_prepare_field($kga['lang']['username'],$column_delimiter,$quote_char);          
        if (isset($columns['cleared']))
          $row[] = csv_prepare_field($kga['lang']['cleared'],$column_delimiter,$quote_char);  

        echo implode($column_delimiter,$row);
        echo "\n";

        // output of data
        foreach ($exportData as $data) {
          $row = array();
          if (isset($columns['date']))
            $row[] = csv_prepare_field(strftime($dateformat,$data['time_in']),$column_delimiter,$quote_char);
          if (isset($columns['from']))
            $row[] = csv_prepare_field(strftime($timeformat,$data['time_in']),$column_delimiter,$quote_char);            
          if (isset($columns['to']))
            $row[] = csv_prepare_field(strftime($timeformat,$data['time_out']),$column_delimiter,$quote_char);           
          if (isset($columns['time']))
            $row[] = csv_prepare_field($data['formattedDuration'],$column_delimiter,$quote_char);          
          if (isset($columns['dec_time']))
            $row[] = csv_prepare_field($data['decimalDuration'],$column_delimiter,$quote_char);     
          if (isset($columns['rate']))
            $row[] = csv_prepare_field($data['rate'],$column_delimiter,$quote_char);          
          if (isset($columns['wage']))
            $row[] = csv_prepare_field($data['wage'],$column_delimiter,$quote_char);                 
          if (isset($columns['budget']))
            $row[] = csv_prepare_field($data['budget'],$column_delimiter,$quote_char);                  
          if (isset($columns['approved']))
            $row[] = csv_prepare_field($data['approved'],$column_delimiter,$quote_char);                  
          if (isset($columns['status']))
            $row[] = csv_prepare_field($data['status'],$column_delimiter,$quote_char);                  
          if (isset($columns['billable']))
            $row[] = csv_prepare_field($data['billable'],$column_delimiter,$quote_char).'%';                       
          if (isset($columns['customer']))
            $row[] = csv_prepare_field($data['customerName'],$column_delimiter,$quote_char);           
          if (isset($columns['project']))
            $row[] = csv_prepare_field($data['projectName'],$column_delimiter,$quote_char);           
          if (isset($columns['activity']))
            $row[] = csv_prepare_field($data['activityName'],$column_delimiter,$quote_char);           
          if (isset($columns['comment']))
            $row[] = csv_prepare_field($data['comment'],$column_delimiter,$quote_char);       
          if (isset($columns['location']))
            $row[] = csv_prepare_field($data['location'],$column_delimiter,$quote_char);      
          if (isset($columns['trackingNumber']))
            $row[] = csv_prepare_field($data['trackingNumber'],$column_delimiter,$quote_char);    
          if (isset($columns['user']))
            $row[] = csv_prepare_field($data['username'],$column_delimiter,$quote_char);          
          if (isset($columns['cleared']))
            $row[] = csv_prepare_field($data['cleared'],$column_delimiter,$quote_char);  

        echo implode($column_delimiter,$row);
        echo "\n";
        }     
    break;



    /**
     * Export as tabular PDF document.
     */
    case 'export_pdf':

        $database->user_set_preferences(array(
          'print_comments'=>isset($_REQUEST['print_comments'])?1:0,
          'print_summary'=>isset($_REQUEST['print_summary'])?1:0,
          'create_bookmarks'=>isset($_REQUEST['create_bookmarks'])?1:0, 
          'download_pdf'=>isset($_REQUEST['download_pdf'])?1:0,
          'customer_new_page'=>isset($_REQUEST['customer_new_page'])?1:0, 
          'reverse_order'=>isset($_REQUEST['reverse_order'])?1:0,
          'pdf_format'=>'export_pdf'),
          'ki_export.pdf.');    

      $exportData = export_get_data($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities,false,$reverse_order,$default_location,$filter_cleared,$filter_type,false,$filter_refundable);

      $orderedExportData = array();
      foreach ($exportData as $row) {
        $customerID = $row['customerID'];
        $projectID = $row['projectID'];

        // create key for customer, if not present
        if (!array_key_exists($customerID,$orderedExportData))
          $orderedExportData[$customerID] = array();

        // create key for project, if not present
        if (!array_key_exists($projectID,$orderedExportData[$customerID]))
          $orderedExportData[$customerID][$projectID] = array();

        // add row
        $orderedExportData[$customerID][$projectID][] = $row;

      }

      require('export_pdf.php');
    break;



    /**
     * Export as a PDF document in a list format.
     */
    case 'export_pdf2':

        $database->user_set_preferences(array(
          'print_comments'=>isset($_REQUEST['print_comments'])?1:0,
          'print_summary'=>isset($_REQUEST['print_summary'])?1:0,
          'create_bookmarks'=>isset($_REQUEST['create_bookmarks'])?1:0, 
          'download_pdf'=>isset($_REQUEST['download_pdf'])?1:0,
          'customer_new_page'=>isset($_REQUEST['customer_new_page'])?1:0, 
          'reverse_order'=>isset($_REQUEST['reverse_order'])?1:0,
          'pdf_format'=>'export_pdf2'),
          'ki_export.pdf.');    
       
      $exportData = export_get_data($in,$out,$filterUsers,$filterCustomers,$filterProjects,$filterActivities,false,$reverse_order,$default_location,$filter_cleared,$filter_type,false,$filter_refundable);

      // sort data into new array, where first dimension is customer and second dimension is project
      $orderedExportData = array();
      foreach ($exportData as $row) {
        $customerID = $row['customerID'];
        $projectID = $row['projectID'];

        // create key for customer, if not present
        if (!array_key_exists($customerID,$orderedExportData))
          $orderedExportData[$customerID] = array();

        // create key for project, if not present
        if (!array_key_exists($projectID,$orderedExportData[$customerID]))
          $orderedExportData[$customerID][$projectID] = array();

        // add row
        $orderedExportData[$customerID][$projectID][] = $row;

      }
      require('export_pdf2.php');
      break;

}

?>
