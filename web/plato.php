<?php

// Show the Density/Temperature chart
// GET Parameters:
// hours = number of hours before now() to be displayed
// days = hours x 24
// weeks = days x 7
// name = iSpindle name
 
include_once("include/common_db.php");
include_once("include/common_db_query.php");

// Check GET parameters (for now: Spindle name and Timeframe to display)
if(!isset($_GET['hours'])) $_GET['hours'] = 0; else $_GET['hours'] = $_GET['hours'];
if(!isset($_GET['name'])) $_GET['name'] = 'iSpindel001'; else $_GET['name'] = $_GET['name'];
if(!isset($_GET['reset'])) $_GET['reset'] = defaultReset; else $_GET['reset'] = $_GET['reset'];
if(!isset($_GET['days'])) $_GET['days'] = 0; else $_GET['days'] = $_GET['days'];
if(!isset($_GET['weeks'])) $_GET['weeks'] = 0; else $_GET['weeks'] = $_GET['weeks'];

// Calculate Timeframe in Hours
$timeFrame = $_GET['hours'] + ($_GET['days'] * 24) + ($_GET['weeks'] * 168);
if($timeFrame == 0) $timeFrame = defaultTimePeriod;
$tftemp = $timeFrame;
$tfweeks = floor($tftemp / 168);
$tftemp -= $tfweeks * 168;
$tfdays = floor($tftemp / 24);
$tftemp -= $tfdays * 24;
$tfhours = $tftemp;

list($angle, $temperature, $dens) = getChartValuesPlato($conn, $_GET['name'], $timeFrame, $_GET['reset']);
list($RecipeName, $show) = getCurrentRecipeName($conn, $_GET['name'], $timeFrame, $_GET['reset']);

?>

<!DOCTYPE html>
<html>
<head>
  <title>iSpindle Data</title>
  <meta http-equiv="refresh" content="120">
  <meta name="Keywords" content="iSpindle, iSpindel, Chart, genericTCP">
  <meta name="Description" content="iSpindle Fermentation Chart">
  <script src="include/jquery-3.1.1.min.js"></script>
  <script src="include/moment.min.js"></script>
  <script src="include/moment-timezone-with-data.js"></script>

<script type="text/javascript">

const chartDens=[<?php echo $dens;?>]
const chartTemp=[<?php echo $temperature;?>]

$(function () 
{
  var chart;
 
  $(document).ready(function() 
  {
        Highcharts.setOptions({
              global: {
                  timezone: 'Australia/Darwin'
              }
          });
                
        chart = new Highcharts.Chart(
        {
            chart:
            {
                renderTo: 'container'
            },
            title:
            {
                text: 'iSpindel: <?php echo $_GET['name'];?>'
            },
            subtitle:
                { text: ' <?php
                  $timetext = 'Temperature and extract content ';
                  if($_GET['reset'])
                  {
                    $timetext .= 'since the last one Reset: ';
                  }                                
                  else
                        {                                                                                           
                    $timetext .= 'the latest ';
                  }
                  if($tfweeks != 0)
                  {
                    $timetext .= $tfweeks . ' Week(n), ';
                  }
                  if($tfdays != 0)
                  {                        
                    $timetext .= $tfdays . ' Day(e), ';                   
                  }                                              
                  $timetext .= $tfhours . ' Hour(n).';
                  echo $timetext;              
		?> '
            },
            xAxis:
            {
                type: 'datetime',
                gridLineWidth: 1,
                title:
            {
                text: 'time of day'
            }
            },
            yAxis: [
                {
                    startOnTick: false,
                    endOnTick: false,
                    min: 0,
                    max: 25,
                    title:
                    {
                        text: 'Extrakt %w/w'
                    },
                    labels:
                    {
                        align: 'left',
                        x: 3,
                        y: 16,
                        formatter: function()
                        {
                            return this.value + '°P'
                        }
                    },
                    showFirstLabel: false
                    },{
                    // linkedTo: 0,
                    startOnTick: false,
                    endOnTick: false,
                    min: -5,
                    max: 35,
                    gridLineWidth: 0,
                    opposite: true,
                    title: {
                        text: 'Temperature'
                    },
                    labels: {
                        align: 'right',
                        x: -3,
                        y: 16,
                        formatter: function() 
                        {
                            return this.value +'°C'
                        }
                    },
                    showFirstLabel: false
                }
            ],
            tooltip:
            {
                crosshairs: [true, true],
                formatter: function() 
                {
                    if(this.series.name == 'Temperature') {
                        const pointData = chartTemp.find(row => row.timestamp === this.point.x)
                        return '<b>Sudname: </b>'+pointData.recipe+'<br>'+'<b>'+ this.series.name +' </b>um '+ Highcharts.dateFormat('%H:%M', new Date(this.x)) +' Uhr:  '+ this.y +'°C';
                    } else {
                        const pointData = chartDens.find(row => row.timestamp === this.point.x)
                        return '<b>Sudname: </b>'+pointData.recipe+'<br>'+'<b>'+ this.series.name +' </b>um '+ Highcharts.dateFormat('%H:%M', new Date(this.x)) +' Uhr:  '+ Math.round(this.y * 100) / 100 +'%';
                    }
                }
            },  
            legend: 
            {
                enabled: true
            },
            credits:
            {
                enabled: false
            },
            series:
            [
                {
                    name: 'Extract',
                    color: '#FF0000',
                    data: chartDens.map(row => [row.timestamp, row.value]),
                    marker: 
                    {
                        symbol: 'square',
                        enabled: false,
                        states: 
                        {
                            hover:
                            {
                                symbol: 'square',
                                enabled: true,
                                radius: 8
                            }
                        }
                    }
                },
                {
                    name: 'Temperature',
                    yAxis: 1,
                    color: '#0000FF',
                    data: chartTemp.map(row => [row.timestamp, row.value]),
                    marker: 
                        {
                            symbol: 'square',
                            enabled: false,
                            states: 
                            {
                                hover:
                                {
                                symbol: 'square',
                                enabled: true,
                                radius: 8
                                }
                            }
                        }

                }
            ] //series      
            });
  });
});
</script>
</head>
<body>
 
<div id="wrapper">
  <script src="include/highcharts.js"></script>
  <div id="container" style="width:98%; height:98%; position:absolute"></div>
</div>
 
</body>
</html>
