<?php

/* 
 Visualizer for iSpindle using genericTCP with mySQL
 Shows mySQL iSpindle data on the browser as a graph via Highcharts:
 http://www.highcharts.com
 
 Data access via mySQL for the charts is defined in here.
 
 For the original project itself, see: https://github.com/universam1/iSpindel
 
 Got rid of deprecated stuff, ready for Debian Stretch now.
 
 Tozzi (stephan@sschreiber.de), Nov 25 2017
 
 Oct 14 2018:
 Added Moving Average Selects, thanks to nice job by mrhyde
 Minor fixes

 Nov 04 2018:
 Update of SQL queries for moving average calculations as usage of multiples spindles at the same time caused an issue and resulted in a mixed value of both spindle data 

 Nov 16 2018
Function getcurrentrecipe rewritten: Recipe Name will be only returned if reset= true or timeframe < timeframe of last reset
Return of variables changed for all functions that return x/y diagram data. Recipe name is added in array and returned to php script

*/

// remove last character from a string


function delLastChar($string="")
{
  $t = substr($string, 0, -1);
  return($t);
}
//Returns name of Recipe for current fermentation - Name can be set with reset.
function getCurrentRecipeName($conn, $iSpindleID='iSpindel000', $timeFrameHours=defaultTimePeriod, $reset=defaultReset)
{
$q_sql1 = mysqli_query($conn, "SELECT Data.Recipe, Data.Timestamp FROM Data WHERE Data.Name = '"
                              .$iSpindleID
                              ."' AND Data.Timestamp >= (SELECT max( Data.Timestamp )FROM Data WHERE Data.Name = '"
                              .$iSpindleID
                              ."' AND Data.ResetFlag = true) LIMIT 1") or die(mysqli_error($conn));


$q_sql2 = mysqli_query($conn, "SELECT Data.Timestamp FROM Data WHERE Data.Name = '"
                              .$iSpindleID
                              ."' AND Timestamp >= date_sub(NOW(), INTERVAL ".$timeFrameHours." HOUR)                                                                                                                                                                          AND Timestamp <= NOW() LIMIT 1") or die(mysqli_error($conn));

  $rows = mysqli_num_rows($q_sql1);


  if ($rows > 0)
  {
    $r_row = mysqli_fetch_array($q_sql1);
    $t_row = mysqli_fetch_array($q_sql2); 
    $RecipeName = '';
    $showCurrentRecipe=false;
    $TimeFrame = $t_row['Timestamp'];
    $ResetTime = $r_row['Timestamp'];
  if ($reset==true)
   {
    $RecipeName = $r_row['Recipe'];
    $showCurrentRecipe=true;
   }
  else
  {  
  if ($ResetTime < $TimeFrame) 
   {
    $RecipeName = $r_row['Recipe'];
    $showCurrentRecipe=true;
   }
   }
    return array($RecipeName, $showCurrentRecipe);
  
  }
}

// Get values from database for selected spindle, between now and timeframe in hours ago                                                                                                                                                      
function getChartValues($conn, $iSpindleID='iSpindel000', $timeFrameHours=defaultTimePeriod, $reset=defaultReset)                                                                                                                             
{                                                                                                                                                                                                                                             
   if ($reset)                                                                                                                                                                                                                                
   {                                                                                                                                                                                                                                          
   $where="WHERE Name = '".$iSpindleID."'                                                                                                                                                                                                     
                  AND Timestamp >= (Select max(Timestamp) FROM Data  WHERE ResetFlag = true AND Name = '".$iSpindleID."')";                                                                                                                   
   }                                                                                                                                                                                                                                          
   else                                                                                                                                                                                                                                       
   {                                                                                                                                                                                                                                          
   $where ="WHERE Name = '".$iSpindleID."'                                                                                                                                                                                                    
            AND Timestamp >= date_sub(NOW(), INTERVAL ".$timeFrameHours." HOUR)                                                                                                                                                               
            AND Timestamp <= NOW()";                                                                                                                                                                                                          
   }                                                                                                                                                                                                                                          
   $q_sql = mysqli_query($conn, "SELECT UNIX_TIMESTAMP(Timestamp) as unixtime, temperature, angle, recipe                                                                                                                                     
                         FROM Data "                                                                                                                                                                                                          
                         .$where                                                                                                                                                                                                              
                         ." ORDER BY Timestamp ASC") or die(mysqli_error($conn));                                                                                                                                                             
                                                                                                                                                                                                                                              
  // retrieve number of rows                                                                                                                                                                                                                  
  $rows = mysqli_num_rows($q_sql);                                                                                                                                                                                                            
  if ($rows > 0)                                                                                                                                                                                                                              
  {                                                                                                                                                                                                                                           
    $valAngle = '';                                                                                                                                                                                                                           
    $valTemperature = '';                                                                                                                                                                                                                     
                                                                                                                                                                                                                                                                                                                                                                                                                                                                   
    // retrieve and store the values as CSV lists for HighCharts                                                                                                                                                                              
    while($r_row = mysqli_fetch_array($q_sql))                                                                                                                                                                                                
    {                                                                                                                                                                                                                                         
      $jsTime = $r_row['unixtime'] * 1000;                                                                                                                                                                                                    
      $valAngle         .= '{ timestamp: '.$jsTime.', value: '.$r_row['angle'].", recipe: \"".$r_row['recipe']."\"},";
      $valTemperature   .= '{ timestamp: '.$jsTime.', value: '.$r_row['temperature'].", recipe: \"".$r_row['recipe']."\"},";
    }                                                                                                                                                                                                                                         
                                                                                                                                                                                                                                              
    // remove last comma from each CSV                                                                                                                                                                                                        
    #$valAngle         = delLastChar($valAngle);                                                                                                                                                                                               
    #$valTemperature   = delLastChar($valTemperature);                                                                                                                                                                                         
                                                                                                                                                                                                                                              
    return array($valAngle, $valTemperature);                                                                                                                                                                                     
  }                                                                                                                                                                                                                                           
}
// Get values from database for selected spindle, between now and timeframe in hours ago and calculate Moving average 
function getChartValues_ma($conn, $iSpindleID='iSpindel000', $timeFrameHours=defaultTimePeriod, $movingtime, $reset=defaultReset)  
{
$where_ma='';                                
   if ($reset)                                 
   {                               
   $where="WHERE Data1.Name = '".$iSpindleID."'
						AND Data1.Timestamp >= (Select max(Timestamp) FROM Data WHERE Data.Name = '".$iSpindleID."' AND Data.ResetFlag = true)";   
						$where_ma="Data2.Timestamp >= (Select max(Data2.Timestamp) FROM Data AS Data2  WHERE Data2.ResetFlag = true AND Data2.Name = '".$iSpindleID."') AND";	
}
	else
	{
   $where ="WHERE Data1.Name = '".$iSpindleID."'
						AND Data1.Timestamp >= date_sub(NOW(), INTERVAL ".$timeFrameHours." HOUR)
						and Data1.Timestamp <= NOW()";  
	} 
   $q_sql = mysqli_query($conn, "SELECT UNIX_TIMESTAMP(Data1.Timestamp) as unixtime, Data1.temperature, Data1.angle, Data1.recipe,
                                (SELECT SUM(Data2.Angle) / COUNT(Data2.Angle)
								FROM Data AS Data2
                                WHERE Data2.Name = '".$iSpindleID."' AND "
				.$where_ma
				." TIMESTAMPDIFF(MINUTE, Data2.Timestamp, Data1.Timestamp) BETWEEN 0 and "
				.$movingtime
				." ) AS mv_angle             
								FROM Data AS Data1 " 
								.$where
								." ORDER BY Data1.Timestamp ASC") or die(mysqli_error($conn));
  // retrieve number of rows              
  $rows = mysqli_num_rows($q_sql);
  if ($rows > 0) 
  {
    $valAngle = ''; 
    $valTemperature = '';
    // retrieve and store the values as CSV lists for HighCharts           
    while($r_row = mysqli_fetch_array($q_sql))
    {
      $jsTime = $r_row['unixtime'] * 1000;
      $valAngle         .= '{ timestamp: '.$jsTime.', value: '.$r_row['mv_angle'].", recipe: \"".$r_row['recipe']."\"},";
      $valTemperature   .= '{ timestamp: '.$jsTime.', value: '.$r_row['temperature'].", recipe: \"".$r_row['recipe']."\"},";
    } 
    // remove last comma from each CSV
//    $valAngle         = delLastChar($valAngle);
//    $valTemperature   = delLastChar($valTemperature);
    return array($valAngle, $valTemperature);
  }
}    

// Get values from database including gravity (Fw 5.0.1 required) for selected spindle, between now and timeframe in hours ago
function getChartValuesPlato($conn, $iSpindleID='iSpindel000', $timeFrameHours=defaultTimePeriod, $reset=defaultReset)
{
   if ($reset)
   {
   	$where="WHERE Name = '".$iSpindleID."' 
            AND Timestamp >= (Select max(Timestamp) FROM Data WHERE ResetFlag = true AND Name = '".$iSpindleID."')";
   }  
   else
   {
   	$where ="WHERE Name = '".$iSpindleID."' 
            AND Timestamp >= date_sub(NOW(), INTERVAL ".$timeFrameHours." HOUR) 
            AND Timestamp <= NOW()";
   }  
   $q_sql = mysqli_query($conn, "SELECT UNIX_TIMESTAMP(Timestamp) as unixtime, temperature, angle, gravity, recipe
                          FROM Data " 
                         .$where 
                         ." ORDER BY Timestamp ASC") or die(mysqli_error($conn));

  // retrieve number of rows
  $rows = mysqli_num_rows($q_sql);
  if ($rows > 0)
  {
    $valAngle = '';
    $valTemperature = '';
    $valGravity = '';

    // retrieve and store the values as CSV lists for HighCharts
    while($r_row = mysqli_fetch_array($q_sql))
    {
      $jsTime = $r_row['unixtime'] * 1000;
      $valAngle         .= '['.$jsTime.', '.$r_row['angle'].'],';
      $valGravity       .= '{ timestamp: '.$jsTime.', value: '.$r_row['gravity'].", recipe: \"".$r_row['recipe']."\"},";
      $valTemperature   .= '{ timestamp: '.$jsTime.', value: '.$r_row['temperature'].", recipe: \"".$r_row['recipe']."\"},";
    }

    // remove last comma from each CSV
//    $valAngle         = delLastChar($valAngle);
//    $valTemperature   = delLastChar($valTemperature);
//    $valGravity       = delLastChar($valGravity);
    return array($valAngle, $valTemperature, $valGravity);
  }
}

// Get current values (angle, temperature, battery)
function getCurrentValues($conn, $iSpindleID='iSpindel000')
{
   $q_sql = mysqli_query($conn, "SELECT UNIX_TIMESTAMP(Timestamp) as unixtime, temperature, angle, battery
                FROM Data
                WHERE Name = '".$iSpindleID."'
                ORDER BY Timestamp DESC LIMIT 1") or die (mysqli_error($conn));
  
  $rows = mysqli_num_rows($q_sql);                                                                                         
  if ($rows > 0)                                                                                                          
  {
    $r_row = mysqli_fetch_array($q_sql);
    $valTime = $r_row['unixtime'];
    $valTemperature = $r_row['temperature'];
    $valAngle = $r_row['angle'];
    $valBattery = $r_row['battery'];
    return array($valTime, $valTemperature, $valAngle, $valBattery);  
  }
}

// Get current values (angle, temperature, battery, rssi)
// Keeping original function unchanged in order to preserve backwards compatibility.
// RSSI is brand new with this version and version 5.8.x of iSpindle Firmware.
function getCurrentValues2($conn, $iSpindleID='iSpindel000')
{                                                          
   $q_sql = mysqli_query($conn, "SELECT UNIX_TIMESTAMP(Timestamp) as unixtime, temperature, angle, battery, `interval`, rssi
                FROM Data                                                                                 
                WHERE Name = '".$iSpindleID."'              
                ORDER BY Timestamp DESC LIMIT 1") or die (mysqli_error($conn));
                                                                               
  $rows = mysqli_num_rows($q_sql);                                                                                            
  if ($rows > 0)                                                                                                      
  {                                                                                                                                        
    $r_row = mysqli_fetch_array($q_sql);    
    $valTime = $r_row['unixtime'];      
    $valTemperature = $r_row['temperature'];  
    $valAngle = $r_row['angle'];                                                                                    
    $valBattery = $r_row['battery']; 
    $valInterval = $r_row['interval'];
    $valRSSI = $r_row['rssi'];
    return array($valTime, $valTemperature, $valAngle, $valBattery, $valInterval, $valRSSI);
  }                                                                 
}  
                        
// Get calibrated values from database for selected spindle, between now and [number of hours] ago
// Old Method for Firmware before 5.x
function getChartValuesPlato4($conn, $iSpindleID='iSpindel000', $timeFrameHours=defaultTimePeriod, $reset=defaultReset)
{
    $isCalibrated = 0;  // is there a calbration record for this iSpindle?
    $valAngle = '';
    $valTemperature = '';
    $valDens = '';
    $const1 = 0;
    $const2 = 0;
    $const3 = 0;
   if ($reset)
   {
   	$where="WHERE Name = '".$iSpindleID."' 
            AND Timestamp >= (Select max(Timestamp) FROM Data  WHERE ResetFlag = true AND Name = '".$iSpindleID."')";
   }  
   else
   {
   	$where ="WHERE Name = '".$iSpindleID."' 
            AND Timestamp >= date_sub(NOW(), INTERVAL ".$timeFrameHours." HOUR) 
            AND Timestamp <= NOW()";
   }  
   
   $q_sql = mysqli_query($conn, "SELECT UNIX_TIMESTAMP(Timestamp) as unixtime, temperature, angle, recipe
                           FROM Data " 
                           .$where 
                          ." ORDER BY Timestamp ASC") or die(mysqli_error($conn));
                     
    // retrieve number of rows
    $rows = mysqli_num_rows($q_sql);
    if ($rows > 0)
    {
     // get unique hardware ID for calibration
     $u_sql = mysqli_query($conn, "SELECT ID FROM Data WHERE Name = '".$iSpindleID."' ORDER BY Timestamp DESC LIMIT 1") or die(mysqli_error($conn));
     $rowsID = mysqli_num_rows($u_sql);
     if ($rowsID > 0)
     {
        // try to get calibration for iSpindle hardware ID
        $r_id = mysqli_fetch_array($u_sql);
        $uniqueID = $r_id['ID'];
        $f_sql = mysqli_query($conn, "SELECT const1, const2, const3 FROM Calibration WHERE ID = '$uniqueID' ") or die(mysqli_error($conn));
        $rows_cal = mysqli_num_rows($f_sql);
        if ($rows_cal > 0)
        {
            $isCalibrated = 1;
            $r_cal = mysqli_fetch_array($f_sql);
            $const1 = $r_cal['const1'];
            $const2 = $r_cal['const2'];
            $const3 = $r_cal['const3'];
        }
     }
     // retrieve and store the values as CSV lists for HighCharts
     while($r_row = mysqli_fetch_array($q_sql))
     {
         $jsTime = $r_row['unixtime'] * 1000;
         $angle = $r_row['angle'];
         $dens = $const1 * pow($angle, 2) + $const2 * $angle + $const3;   // complete polynome from database
                         
         $valAngle         .= '['.$jsTime.', '.$angle.'],';
         $valDens          .= '{ timestamp: '.$jsTime.', value: '.$dens.", recipe: \"".$r_row['recipe']."\"},";
         $valTemperature   .= '{ timestamp: '.$jsTime.', value: '.$r_row['temperature'].", recipe: \"".$r_row['recipe']."\"},";

 

    }
     // remove last comma from each CSV
 //    $valAngle         = delLastChar($valAngle);
 //    $valTemperature   = delLastChar($valTemperature);
     return array($isCalibrated, $valDens, $valTemperature, $valAngle);
    }
 }
 
// Get calibrated values from database for selected spindle, between now and [number of hours] ago
// Old Method for Firmware before 5.x
function getChartValuesPlato4_ma($conn, $iSpindleID='iSpindel000', $timeFrameHours=defaultTimePeriod, $movingtime, $reset=defaultReset)
{
    $isCalibrated = 0;  // is there a calbration record for this iSpindle?
    $valAngle = '';
    $valTemperature = '';
    $valDens = '';
    $const1 = 0;
    $const2 = 0;
    $const3 = 0;
    $where_ma='';
   if ($reset)
   {
   	$where="WHERE Data1.Name = '".$iSpindleID."' 
            AND Data1.Timestamp >= (Select max(Timestamp) FROM Data  WHERE ResetFlag = true AND Name = '".$iSpindleID."')";
	$where_ma="Data2.Timestamp >= (Select max(Data2.Timestamp) FROM Data AS Data2  WHERE Data2.ResetFlag = true AND Data2.Name = '".$iSpindleID."') AND";
   }  
   else
   {
   	$where ="WHERE Data1.Name = '".$iSpindleID."' 
            AND Data1.Timestamp >= date_sub(NOW(), INTERVAL ".$timeFrameHours." HOUR) 
            AND Data1.Timestamp <= NOW()";
   }  
   $q_sql = mysqli_query($conn, "SELECT UNIX_TIMESTAMP(Data1.Timestamp) as unixtime, Data1.temperature, Data1.angle, Data1.recipe,
                           (SELECT SUM(Data2.Angle) / COUNT(Data2.Angle)
							FROM Data AS Data2
                            WHERE Data2.Name = '".$iSpindleID."' AND "
				.$where_ma
				." TIMESTAMPDIFF(MINUTE, Data2.Timestamp, Data1.Timestamp) BETWEEN 0 and "
				.$movingtime
				." ) AS mv_angle
						    FROM Data AS Data1 " 
                           .$where 
                          ." ORDER BY Data1.Timestamp ASC") or die(mysqli_error($conn));
                     
    // retrieve number of rows
    $rows = mysqli_num_rows($q_sql);
    if ($rows > 0)
    {
     // get unique hardware ID for calibration
     $u_sql = mysqli_query($conn, "SELECT ID FROM Data WHERE Name = '".$iSpindleID."' ORDER BY Timestamp DESC LIMIT 1") or die(mysqli_error($conn));
     $rowsID = mysqli_num_rows($u_sql);
     if ($rowsID > 0)
     {
        // try to get calibration for iSpindle hardware ID
        $r_id = mysqli_fetch_array($u_sql);
        $uniqueID = $r_id['ID'];
        $f_sql = mysqli_query($conn, "SELECT const1, const2, const3 FROM Calibration WHERE ID = '$uniqueID' ") or die(mysqli_error($conn));
        $rows_cal = mysqli_num_rows($f_sql);
        if ($rows_cal > 0)
        {
            $isCalibrated = 1;
            $r_cal = mysqli_fetch_array($f_sql);
            $const1 = $r_cal['const1'];
            $const2 = $r_cal['const2'];
            $const3 = $r_cal['const3'];
        }
     }
     // retrieve and store the values as CSV lists for HighCharts
     while($r_row = mysqli_fetch_array($q_sql))
     {
         $jsTime = $r_row['unixtime'] * 1000;
         $angle = $r_row['mv_angle'];
         $dens = $const1 * pow($angle, 2) + $const2 * $angle + $const3;   // complete polynome from database
                         
         $valAngle         .= '['.$jsTime.', '.$angle.'],';
         $valDens          .= '{ timestamp: '.$jsTime.', value: '.$dens.", recipe: \"".$r_row['recipe']."\"},";
         $valTemperature   .= '{ timestamp: '.$jsTime.', value: '.$r_row['temperature'].", recipe: \"".$r_row['recipe']."\"},";


     }
     // remove last comma from each CSV
//     $valAngle         = delLastChar($valAngle);
//     $valTemperature   = delLastChar($valTemperature);
     return array($isCalibrated, $valDens, $valTemperature, $valAngle);
    }
 }

?>

