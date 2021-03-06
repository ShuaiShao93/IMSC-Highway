<?php 
$host = "host=osm-workspace-2.cfmyklmn07yu.us-west-2.rds.amazonaws.com";
$port = "port=5432";
$dbname = "dbname=osm";
$credentials = "user=ds password=928Sbi2sl";

$links_table = "staging_links";
$nodes_table = "staging_nodes";
$sensors_table = "highway_congestion_config";
$mapping_table = "ss_highway_mapping";
$pattern_table = "ss_highway_pattern";


$db  =  pg_connect( "$host $port $dbname $credentials" ); 
    if(!$db){
      echo "Error : Unable to open database\n";
    } 

date_default_timezone_set("America/Los_Angeles");
$weekday = date("l");


if ($_SERVER["REQUEST_METHOD"] == "GET"):

$road_similarity = array();

$sql = 'SELECT road_name, direction, from_postmile, similarity from '.$pattern_table.' WHERE day='."'".$weekday."'";
$ret = pg_query($db, $sql);
if(!$ret){
    echo pg_last_error($db);
    exit;
}

while($row = pg_fetch_row($ret)){
    $road_name = (int)$row[0];
    if (!array_key_exists($road_name, $road_similarity)){
        $road_similarity[$road_name] = array();
    }
    $direction = (int)$row[1];
    if (!array_key_exists($direction, $road_similarity[$road_name])){
        $road_similarity[$road_name][$direction] = array(array(),array(),array(),array(),array());
    }
    $section = ((int)$row[2])/3;
    $raw_similarity = substr($row[3], 1, strlen($row[3])-2);
    $similarity = explode(",", $raw_similarity);
    for ($i = 0; $i < 5; $i ++){
        if ($similarity[$i] != "NULL"){
            array_push($road_similarity[$road_name][$direction][$i], $similarity[$i]);
        }
    }
}

foreach ($road_similarity as $road_name => $s){
    ksort($road_similarity[$road_name]);
    foreach ($road_similarity[$road_name] as $direction => $si){
        for ($i = 0; $i < 5; $i ++){
            if (count($road_similarity[$road_name][$direction][$i]) == 0){
                $road_similarity[$road_name][$direction][$i] = "null";
            }
            else{
                $road_similarity[$road_name][$direction][$i] = round(array_sum($road_similarity[$road_name][$direction][$i]) / count($road_similarity[$road_name][$direction][$i]), 2);
            }
        }
    }
}
ksort($road_similarity);


?>

<head>
<title>Patterns Comparison</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link href="pattern_all.css" rel="stylesheet" type="text/css" media="screen"/>
<script src="Chart.js"></script>
<script src="Chart.min.js"></script>
<script src="jquery-2.1.4.js"></script>
</head>
<body>
<h1 style = 'text-align:center'>Pattern Comparison</h1>
<div class="main_div" style="width:100%;text-align:center;">
<button onclick='all_click()'>All Pattern Chart</button>
<table class="main" border="0">
    <tr>
        <th rowspan="2">Highway_Name</th>
        <th rowspan="2">Direction</th>
        <th rowspan="2">Day</th>
        <th colspan="5">Similarity</th>
    </tr>
    <tr>
        <th>Moring(6-9)</th>
        <th>Noon(9-15)</th>
        <th>Afternoon(15-18)</th>
        <th>Evening(18-21)</th>
        <th>Average</th>
    </tr>           
    <?php 
        foreach ($road_similarity as $road_name=>$directions){
            foreach ($directions as $direction=>$similarity){
                if ($direction == 0){
                    $dir = "North";
                }
                elseif ($direction == 1){
                    $dir = "South";
                }
                elseif ($direction == 2){
                    $dir = "East";
                }
                elseif ($direction == 3){
                    $dir = "West";
                }
                foreach ($similarity as $key=>$value){
                    if ($value < 8){
                        $similarity[$key] = "<td bgcolor='#FFFF00'>$value";
                    }
                    else{
                        $similarity[$key] = "<td>$value";
                    }
                }
                echo "<tr id='R$road_name"."_"."$direction' onclick=\"click_road('$road_name','$direction')\"><td>".$road_name."</td><td>$dir</td><td>".$weekday."</td>$similarity[1]</td>$similarity[2]</td>$similarity[3]</td>$similarity[4]</td>$similarity[0]</td></tr>";
            }
        }
    ?>
</table>    
</div>
</body>
<script type="text/javascript">
    function click_road(road_name, direction){
        var dir;
        if (direction == 0){
            dir = "North";
        }
        else if(direction == 1){
            dir = "South";
        }
        else if (direction == 2){
            dir = "East";
        }
        else if (direction == 3){
            dir = "West";
        }
        var selected_road = null;
        if ($(".section_row").length > 0){
            selected_road = $(".section_row").prev();
            $(".section_row").remove();
        }
        if (!selected_road || selected_road.attr('id') != "R"+road_name+"_"+direction){
            var php_self = "<?php echo $_SERVER['PHP_SELF'] ?>";
            $.post(php_self, {"road_name": road_name, "direction": direction}, function(data){
                var table2html = "<tr class='section_row'><td colspan='8'><table border='0' class='section' width='80%'><tr><th rowspan='2'>Highway_Name</th><th rowspan='2'>Direction</th><th rowspan='2'>From_Postmile</th><th rowspan='2'>To_Postmile</th><th rowspan='2'>Day</th><th colspan='5'>Similarity</th></tr><tr><th>Moring(6-9)</th><th>Noon(9-15)</th><th>Afternoon(15-18)</th><th>Evening(18-21)</th><th>Average</th></tr>";
                var section_similarity = data;
                for (var section=0; section < section_similarity.length; section ++){
                    if (section_similarity[section]){
                        for (var i=0; i < 5; i++){
                            if (section_similarity[section][i] < 8){
                                section_similarity[section][i] = "<td bgcolor='#FFFF00'>" + section_similarity[section][i];
                            }
                            else{
                                section_similarity[section][i] = "<td>" + section_similarity[section][i];
                            }
                        }
                        table2html += "<tr id='S"+road_name+"_"+direction+"_"+section+"' onclick=\"click_section('"+road_name+"','"+direction+"','"+section+"');\"><td>"+road_name+"</td><td>"+dir+"</td><td>"+(section*3)+"</td><td>"+(section*3+3)+"</td><td><?php echo $weekday; ?></td>"+section_similarity[section][1]+"</td>"+section_similarity[section][2]+"</td>"+section_similarity[section][3]+"</td>"+section_similarity[section][4]+"</td>"+section_similarity[section][0]+"</td></tr>"; 
                    }
                }
                table2html += "</table></td></tr>";
                $("#R"+road_name+"_"+direction).after(table2html);
            },
            "json");
        }
    }
    
    function click_section(road_name, direction, section){
        var selected_section = null;
        if ($(".color_row").length > 0){
            selected_section = $(".color_row").prev();
            $(".color_row").remove();
            $(".chart_row").remove();
        }
        if (!selected_section || selected_section.attr('id') != "S"+road_name+"_"+direction+"_"+section){
            var php_self = "<?php echo $_SERVER['PHP_SELF']; ?>";
            $.post(php_self, {"road_name": road_name, "direction":direction, "section":section}, function(data){
                var chart_html = "<tr class='color_row'><td colspan='10'><table align='center'><tr><th>Realtime_pattern: </th><th><hr width=50 noshade color='#0000FF' /></th><th>&nbsp;Historical_pattern:</th><th><hr width=50 noshade color='#00FF00'></th></tr></table><button onclick=\"click_detail('"+road_name+"','"+direction+"','"+section+"');\">Details</button></td></tr><tr class='chart_row'><td colspan='10'><canvas id='section_chart' width='800%' height='400' /></td></tr>";
                $("#S"+road_name+"_"+direction+"_"+section).after(chart_html);

                var ctx = document.getElementById("section_chart").getContext("2d");
                var chart_data = {
                    labels : ["6:00","6:15","6:30","6:45","7:00","7:15","7:30","7:45","8:00","8:15","8:30","8:45","9:00","9:15","9:30","9:45",
                "10:00","10:15","10:30","10:45","11:00","11:15","11:30","11:45","12:00","12:15","12:30","12:45","13:00","13:15","13:30","13:45",
                "14:00","14:15","14:30","14:45","15:00","15:15","15:30","15:45","16:00","16:15","16:30","16:45","17:00","17:15","17:30","17:45",
                "18:00","18:15","18:30","18:45","19:00","19:15","19:30","19:45","20:00","20:15","20:30","20:45"
                    ],
                    datasets : [
                        {
                        fillColor : "rgba(255,255,255,0)",
                        strokeColor : "rgba(0,0,255,1)",
                        pointColor : "rgba(0,180,205,1)",
                        pointStrokeColor : "#fff",
                        data : data['realtime_pattern']
                        },	
                        {
                        fillColor : "rgba(255,255,255,0)",
                        strokeColor : "rgba(0,255,0,1)",
                        pointColor : "rgba(34,140,34,1)",
                        pointStrokeColor : "#fff",
                        data : data['historical_pattern']
                        }
                    ]
                }
                var myNewChart = new Chart(ctx).Line(chart_data, {
                    scaleOverride :true ,   
                    scaleSteps : 11,        
                    scaleStepWidth : 10,   
                    scaleStartValue : 20,    
                    pointDot : true,        
                    pointDotRadius : 5,      
                    pointDotStrokeWidth : 1,
                    datasetStrokeWidth : 3, 
                    animation : true,       
                    animationSteps : 60    
                } );
                
            },
            "json");
        }
    }
    
    function click_detail(road_name, direction, section){
        var detail_win = window.open('','detail_table','height=300, width=500');
        detail_win.document.write("<p id='remind'>Wait for processing please!</p>");
  
        var php_self = "<?php echo $_SERVER['PHP_SELF']; ?>";
        $.post(php_self, {"road_name": road_name, "direction":direction, "section":section, "detail":true}, function(data){
            detail_win.document.getElementById("remind").remove();
            var detail_html = " \
                <head>  \
                    <title>Section Detail</title> \
                    <style type='text/css'> \
                        th, td{ \
                            padding: 6px;   \
                        }   \
                    </style>    \
                </head>   \
                <body>  \
                    <table style='text-align:center' border=1> \
                        <tr>    \
                            <th>Link_id</th>    \
                            <th>Way_id</th> \
                            <th>Start_Nodeid</th> \
                            <th>Start_Location</th> \
                            <th>End_Nodeid</th> \
                            <th>End_Location</th>   \
                            <th>Length</th>   \
                            <th>Sensor_id</th>  \
                            <th>Sensor_Location</th>    \
                        </tr>";
            for (link_id in data){
                var sensors = data[link_id]['sensors'];
                var sensor_ids = [];
                for (sensor_id in sensors){
                    sensor_ids.push(sensor_id);
                }
                detail_html += "    \
                    <tr>    \
                        <td rowspan="+sensor_ids.length+">"+link_id+"</td>    \
                        <td rowspan="+sensor_ids.length+">"+data[link_id]['wayid']+"</td>    \
                        <td rowspan="+sensor_ids.length+">"+data[link_id]['from_nodeid']+"</td>  \
                        <td rowspan="+sensor_ids.length+">"+data[link_id]['from_loc']+"</td>  \
                        <td rowspan="+sensor_ids.length+">"+data[link_id]['to_nodeid']+"</td>  \
                        <td rowspan="+sensor_ids.length+">"+data[link_id]['to_loc']+"</td>    \
                        <td rowspan="+sensor_ids.length+">"+data[link_id]['length']+"</td>    \
                        <td>"+sensor_ids[0]+"</td>    \
                        <td>"+sensors[sensor_ids[0]]['loc']+"</td>  \
                    </tr>   \
                ";
                sensor_ids.shift();
                for (idx in sensor_ids){
                    sensor_id = sensor_ids[idx];
                    detail_html += "    \
                        <tr>    \
                            <td>"+sensor_id+"</td>    \
                            <td>"+sensors[sensor_id]['loc']+"</td>  \
                        </tr>   \
                    ";
                }
            }
    detail_html+= "</table>    \
                </body> \
            ";
            
            detail_win.document.write(detail_html);
            //detail_win.document.write(data);
        },
        "json");
    }
    
    function all_click(){
        var all_win = window.open('','all_chart','height=500, width=1100');
        all_win.document.write("<p id='remind'>Wait for processing please! About 2 minutes!</p>");
        
        var php_self = "<?php echo $_SERVER['PHP_SELF']?>";
        $.post(php_self, {"all": true}, function(data){
            all_win.document.getElementById("remind").remove();
            all_win.document.write("<canvas id='all_chart' width='1000px' height='400px'/>");
            all_win.document.close();
            
            var data_sets = [];
            for (var i=0; i<data.length; i++){
                data_sets.push({
                    "fillColor" : "rgba(255,255,255,0)",
                    "strokeColor" : "rgba(0,0,255,1)",
                    "pointColor" : "rgba(0,180,205,1)",
                    "pointStrokeColor" : "#fff",
                    "data" : data[i][3]});
            }
            
            
            var ctx = all_win.document.getElementById("all_chart").getContext("2d");
            var chart_data = {
                labels : ["6:00","6:15","6:30","6:45","7:00","7:15","7:30","7:45","8:00","8:15","8:30","8:45","9:00","9:15","9:30","9:45",
            "10:00","10:15","10:30","10:45","11:00","11:15","11:30","11:45","12:00","12:15","12:30","12:45","13:00","13:15","13:30","13:45",
            "14:00","14:15","14:30","14:45","15:00","15:15","15:30","15:45","16:00","16:15","16:30","16:45","17:00","17:15","17:30","17:45",
            "18:00","18:15","18:30","18:45","19:00","19:15","19:30","19:45","20:00","20:15","20:30","20:45"
                ],
                datasets : data_sets
            }
            var myNewChart = new Chart(ctx).Line(chart_data, {
                scaleOverride :true ,   
                scaleSteps : 12,        
                scaleStepWidth : 10,   
                scaleStartValue : 10,    
                pointDot : true,        
                pointDotRadius : 5,      
                pointDotStrokeWidth : 1,
                datasetStrokeWidth : 3, 
                animation : true,       
                animationSteps : 60  
            });
        },
        "json");
    }
</script>
<?php
elseif ($_SERVER["REQUEST_METHOD"] == "POST"):
    ini_set("display_errors", "off");

    if (isset($_POST["all"])):
        $sql = "select road_name, direction, from_postmile, realtime_pattern from $pattern_table where day = '$weekday' and similarity[1] > 8 and similarity[2] > 8 and similarity[3] > 8 and similarity[4] > 8 and similarity[5] > 8";
        $ret = pg_query($db, $sql);
        if(!$ret){
            echo pg_last_error($db);
            exit;
        }
        $all_pattern = array();
        while($row = pg_fetch_row($ret)){
            $road_name = $row[0];
            $direction = $row[1];
            $from_postmile = $row[2];
            $raw_realtime = substr($row[3], 1, strlen($row[3])-2);
            $realtime_pattern = explode(',', $raw_realtime);
            foreach($realtime_pattern as $key=>$value){
                if($value == "NULL"){
                    $realtime_pattern[$key] = null;
                }
            }
            
            array_push($all_pattern, array($road_name, $direction, $from_postmile, $realtime_pattern));
        }

        echo json_encode($all_pattern);

    else:
        $road_name = $_POST["road_name"];
        $direction = $_POST["direction"];

        if (!isset($_POST["section"])):
            $section_similarity = array();

            $sql = 'SELECT from_postmile, to_postmile, similarity from '.$pattern_table.' WHERE road_name='."'".$road_name."'".' AND direction='.$direction.' AND day='."'".$weekday."'";
            $ret = pg_query($db, $sql);
            if(!$ret){
                echo pg_last_error($db);
                exit;
            }
            while($row = pg_fetch_row($ret)){
                $from_postmile = (int)$row[0];
                $to_postmile = (int)$row[1];
                $section = $from_postmile / 3;
                $raw_similarity = substr($row[2], 1, strlen($row[2])-2);
                $similarity = explode(",", $raw_similarity);
                foreach ($similarity as $key => $value) {
                    if($value=="NULL"){
                        $similarity[$key] = null;
                    }
                    else{
                        $similarity[$key] = round($similarity[$key], 2);
                    }
                }

                if ($section != count($section_similarity)){
                    for ($i = count($section_similarity); $i<$section; $i++){
                        $section_similarity[$i] = null;
                    }
                }
                $section_similarity[$section] = $similarity;
            }

            echo json_encode($section_similarity);
        elseif (!isset($_POST["detail"])):
            $section = $_POST["section"];
            $from_postmile = (int)$section * 3;

            $patterns = array();

            $sql = "SELECT realtime_pattern, historical_pattern from ".$pattern_table." WHERE road_name = '$road_name' AND direction = $direction AND from_postmile = $from_postmile AND day = '$weekday'";
            $ret = pg_query($db, $sql);
            if(!$ret){
                echo pg_last_error($db);
                exit;
            }
            $row = pg_fetch_row($ret);
            $raw_realtime_pattern = substr($row[0], 1, strlen($row[0])-2);
            $realtime_pattern = explode(",", $raw_realtime_pattern);
            $raw_historical_pattern = substr($row[1], 1, strlen($row[1])-2);
            $historical_pattern = explode(",", $raw_historical_pattern);

            foreach ($realtime_pattern as $key => $value) {
                if($value=="NULL")
                    $realtime_pattern[$key] = null;
            }
            foreach ($historical_pattern as $key => $value) {
                if($value=="NULL")
                    $historical_pattern[$key] = null;
            }

            $patterns['realtime_pattern'] = $realtime_pattern;
            $patterns['historical_pattern'] = $historical_pattern;

            echo json_encode($patterns);

        elseif (isset($_POST["detail"])):
            $section = $_POST["section"];
            $from_postmile = (int)$section * 3;

            $mapping = array();
            $sensors = array();

            $sql = "SELECT link_id,start_nodeid,ST_X(start_loc),ST_Y(start_loc),end_nodeid,ST_X(end_loc),ST_Y(end_loc),length,wayid,sensor_id,ST_X(sensor_loc),ST_Y(sensor_loc) from ".$mapping_table." WHERE road_name = '$road_name' AND direction = $direction AND from_postmile = $from_postmile";
            $ret = pg_query($db, $sql);
            if(!$ret){
                echo pg_last_error($db);
                exit;
            }
            while($row = pg_fetch_row($ret)){
                $link_id = $row[0];
                $from_nodeid = $row[1];
                $from_loc = $row[3].', '.$row[2];
                $to_nodeid = $row[4];
                $to_loc = $row[6].', '.$row[5];
                $length = $row[7];
                $wayid = $row[8];
                $sensor_id = $row[9];
                $sensor_loc = $row[11].', '.$row[10];
                if (!array_key_exists($link_id, $mapping)){
                    $mapping[$link_id] = array();
                    $mapping[$link_id]['from_nodeid'] = $from_nodeid;
                    $mapping[$link_id]['from_loc'] = $from_loc;
                    $mapping[$link_id]['to_nodeid'] = $to_nodeid;
                    $mapping[$link_id]['to_loc'] = $to_loc;
                    $mapping[$link_id]['length'] = $length;
                    $mapping[$link_id]['wayid'] = $wayid;
                    $mapping[$link_id]['sensors'] = array();
                }
                $mapping[$link_id]['sensors'][$sensor_id] = array();
                $mapping[$link_id]['sensors'][$sensor_id]['loc'] = $sensor_loc;
            }

            echo json_encode($mapping);

        endif;
    endif;
endif;
?>