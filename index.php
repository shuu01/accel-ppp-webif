<!doctype html>
<html>
<head>
<title>accel-pppd</title>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.1/themes/cupertino/jquery-ui.css">
<link rel="stylesheet" href="//cdn.datatables.net/1.10.2/css/jquery.dataTables.css">
<link href="//medialize.github.io/jQuery-contextMenu/src/jquery.contextMenu.css" rel="stylesheet" type="text/css" />
<script src="//code.jquery.com/jquery-2.1.1.min.js"></script>
<script src="//code.jquery.com/ui/1.11.1/jquery-ui.min.js"></script>
<script src="//cdn.datatables.net/1.10.2/js/jquery.dataTables.min.js"></script>
<script src="http://code.highcharts.com/highcharts.js"></script>
<script src="http://code.highcharts.com/highcharts-more.js"></script>
<style type="text/css">
body {
    font-size: 10px;
}
</style>
</head>
<body>
<div id="ifchart"></div>
<div id="tcpdump" style="display: none"></div>
<div id="loadingdialog" style="text-align:center">Please wait...</div>
<div id="logindialog">
<div id="message"></div>
<form id="flogin">
<input type=hidden name=action value="login">
<table>
<tr><td>Login:</td><td><input type="text" name="login" id="login"></td></tr>
<tr><td>Password:</td><td><input type="password" name="password" id="password"></td></tr>
</table>
</div>
<!-- MAIN SCREEN -->
<div id="mainscreen">
<ul>
    <li><a href="#tabmain">Main</a></li>
    <li><a href="#tabusers">Users</a></li>
    <li><a href="mrtg/index.html">Graphs</a></li>
    <li><a href="#tablogs">Logs</a></li>
    <li><a href="#tablogout">Logout</a></li>
  </ul>
  <div id="tabmain">
  <input type=button id="autorefresh" value="Autorefresh: OFF">
  <pre></pre>
  </div>
  <div id="tabusers">
  </div>
  <div id="tablogs">
     <input type="text" id="search" value="" placeholder="Search by account..."><input type="button" id="searchbutton" value="ok">
     <p>
     <div id="logs" style="font-family: monospace"></div>
  </div>
  <div id="tablogout">
  </div>
</div>
<!-- MAIN SCREEN EOF -->

<script>
var autorefresh = 0;
var oldtab = "tabmail";
var table = null;
var chart; // global
var chart_interface; // TODO: change this ugly global variable?

function requestData() {
    $.ajax({
        url: 'data.php',
	data: { action: "ifstat", interface: chart_interface },
	type: "POST",
        success: function(point) {
	    if (chart == null) {
		return;
	    }
            var series = chart.series[0],
                shift = series.data.length > 70;// shift if the series is 
                                                 // longer than 70
	    if ( typeof requestData.txbytes != 'undefined' ) {
		var stampdiff = point.stamp - requestData.stamp;
		var speedtx = (point.txbytes - requestData.txbytes) * 1000 * 8 / stampdiff;
		var speedrx = (point.rxbytes - requestData.rxbytes) * 1000 * 8 / stampdiff;
            	// add the point
            	chart.series[0].addPoint([point.stamp, speedtx], true, shift);
		chart.series[1].addPoint([point.stamp, speedrx], true, shift);
	    }
	    requestData.stamp = point.stamp;
            requestData.txbytes = point.txbytes;
	    requestData.rxbytes = point.rxbytes;
            
            // call it again after one second
            setTimeout(requestData, 1000);    
        },
        cache: false
    });
}

function showchart (rate, username) {
	var winW = $(window).width() - 180;
	var winH = $(window).height() - 180;

	$("#ifchart").dialog({ height: winH, width: winW, modal: true,
		close: function( event, ui ) {
			delete requestData.txbytes;
			delete requestData.rxbytes;
			chart.destroy();
			chart = null;
		}
	 });
	Highcharts.setOptions({global: {useUTC: false}});
	chart = new Highcharts.Chart({

        chart: {
            renderTo: 'ifchart',
            defaultSeriesType: 'line',
            events: {
                load: requestData
            }
        },
        title: {
            text: 'user: ' + username + ' || limit: ' + rate + ' kbps'
        },
        xAxis: {
            type: 'datetime',
            tickPixelInterval: 150,
            minRange: 72000
        },
        yAxis: {
            minPadding: 0.2,
            maxPadding: 0.2,
            max: (rate * 1024) + (rate * 1024)/2,
            endOnTick: false,
            title: {
                text: 'Bps',
                margin: 80
            }
        },
	plotOptions: {
            line: {
                dataLabels: {
                    enabled: true,
                    formatter: function() {
                        var value = (this.y);
                        if (value > 1024 * 1024) return (value/1024/1024).toPrecision(3) + 'M';
                        if (value > 1024) return Math.floor(value/1024) + 'K';
		        return value;
                    },
                },
            enableMouseTracking: false
            }
        },
        series: [{
            name: 'TX Speed',
            data: []
        }, {
            name: 'RX Speed',
            data: []
        }]
    });
}

function loaddump(intf) {
    var winW = $(window).width() - 180;
    var winH = $(window).height() - 180;
    $("#tcpdump").html("<textarea id='output' name='output' style='width: 95%; height: 95%; max-width: 95%; max-height: 95%' readonly></textarea>");
    $("#tcpdump").dialog({
        title: intf,
        height: winH,
        width: winW,
        modal: true,
        buttons: [
            {
                text: "Start",
                id: "btn-start",
                click: function() {
                    $("#btn-start").button("disable");
                    $.ajax({
                      url: 'data.php',
                      data: { action: "start_dump", interface: intf },
                      type: "POST",
                      success: function(ret) {
                          $("#output").text("tcpdump is running...");
                      }
                    });
                    setTimeout(function() { $("#btn-stop").button("enable"); }, 3000);
                }
            },
            {
                text: "Stop",
                id: "btn-stop",
                disabled: true,
                click: function() {
                    $("#btn-stop").button("disable");
                    $("#btn-start").button("enable");
                    $.ajax({
                      url: 'data.php',
                      data: { action: "stop_dump", interface: intf },
                      type: "POST",
                      success: function(ret) {
                          $("#output").text(ret.output);
                      }
                    });
                }
            }
        ],
        close: function() {
            $.ajax({
              url: 'data.php',
              data: { action: "stop_dump", interface: intf },
              type: "POST"
            });
        }
    });
}

function reply_click(obj) {
    var username = $(obj).closest("tr").children("td:nth-of-type(2)").text();
    var intf = $(obj).closest("tr").children("td:nth-of-type(1)").text();
    var rate = $(obj).closest("tr").children("td:nth-of-type(5)").text().split("/")[0];
    var csid = $(obj).closest("tr").children("td:nth-of-type(3)").text();
    var id = obj.id;
    if (id == "watch" && intf) {
        chart_interface = intf;
        console.log('interface ' + intf);
        showchart(rate, username);
    }
    else if (id == "dump") {
        loaddump(intf);
    }
    else {
        if (!intf) { id = "drop" };
        $("#loadingdialog").dialog('open');
        $(".ui-dialog-titlebar").hide();
        $.post("data.php", { action: id, interface: intf, csid: csid }, function (ret) {
            setTimeout(function() {
                $(".act").val('');
                $("#loadingdialog").dialog('close');
            }, 1000);
        });
    }
}


function loadmain() {
        $.post("data.php", { action: "stat" }, function (ret) {
                $("#tabmain pre").html(ret.output);
        });
	if (autorefresh == 1) {
		setTimeout(loadmain, 1000);
	}
}

function showlogs() {
    $("#searchbutton").button().click( function(ev) {
    	var value = $("#search").val();
    	$.post("data.php", { action: "showlogs", search: value }, function (ret) {
        $("#logs").html(ret.output);
    	});
    });
}

function activator (event, ui) {
    var tabname = ui.newPanel.attr('id');

    console.log('Activating tab ' + tabname);
    if (tabname == "tablogout") {
        $.post("data.php", { action: "logout" }, function (returned) {
            location.reload();
        });
    }
    if (oldtab == "tabusers") {
        table.clear();
        table.destroy();
        table = null;
        $("#tabusers").html('');
    }
    if (tabname == "tabmain") { loadmain(); }
    if (tabname == "tablogs") { showlogs(); }
    if (tabname == "tabusers") {
        $("#loadingdialog").dialog('open');
        $(".ui-dialog-titlebar").hide();
        $.post("data.php", { action: "users" }, function (ret) {
			//console.log(ret.output);
            $("#tabusers").html('<table id="tusers" class="cell-border compact"><thead><tr><th>ifname</th><th>username</th><th>calling-sid</th><th>ip</th><th>rate-limit</th><th>type</th><th>comp</th><th>state</th><th>uptime</th><th>Operation</th></tr></thead></table>');
            $("#tabusers").prepend('<button id="refreshusers">Refresh</button>');
            $("#refreshusers").button().click( function (e) {
                $("#loadingdialog").dialog('open');
                $(".ui-dialog-titlebar").hide();
                $("#mainscreen").tabs("option", "active", 0);
                setTimeout(function () { $("#mainscreen").tabs("option", "active", 1); }, 200);
            });

            table = $("#tusers").DataTable({ "data": ret.output, "iDisplayLength": 25, "columnDefs":[
            // The `data` parameter refers to the data for the cell (defined by the
            // `data` option, which defaults to the column being worked with, in
            // this case `data: 0`.
                { "render": function ( data, type, row ) { return '<a href="http://' + data + '" target="_blank">' + data + '</a>'; },
                  "targets": [2, 3]
                },
                { "targets": -1,
                  "width": "180px",
                  "data": null,
                  "defaultContent": '<div style="text-align: center; width: 100%"><button id="watch" style="display: inline-block; float: left" onClick="reply_click(this)">Watch</button><button id="dump" onClick="reply_click(this)">Dump</button><button id="kill" style="float: right; display: inline-block; color: red" onClick="reply_click(this)">Kill</button></div>'
                }
            ], });
            $("#loadingdialog").dialog('close');
        });
    }
}

function showmainscreen () {
	$("#mainscreen").show().tabs({
		activate: activator
	});
	loadmain();
	$("#autorefresh").button().click( function (ev) {
		ev.preventDefault();
		if (autorefresh == 0) {
			$("#autorefresh").prop('value', 'Autorefresh: ON');
			autorefresh = 1;
			setTimeout(loadmain, 1000);
		} else {
			$("#autorefresh").prop('value', 'Autorefresh: OFF');
			autorefresh = 0;
		}
	});
}

function trylogin(event) {
	event.preventDefault();
	var data = $('#flogin').serializeArray();
	$.post("data.php", data, function (returned) {
		if (returned.status == "OK") {
			$("#logindialog").dialog("close");
			showmainscreen();
		} else {
			$("#message").html(returned.status).css('color', 'red');
			$("#flogin").hide();
			// Remove message after while
			setTimeout(function(){ $("#message").html(''); $("#flogin").show(); }, 1000);
		}
	});
}

function prelogin(data) {
	if (data.authenticated == 1) {
		// We are already authenticated
		showmainscreen();
	} else {
		if (data.login) {
			$("#login").val(data.login);
		}
		$("#logindialog").dialog('open');
	}
}

$(document).ready(function(){
	$("#mainscreen").hide();
	$("#logindialog").dialog({ autoOpen: false, buttons: [ { text: "Login", click: trylogin } ], title: "accel-ppp login", open: function() {
          $("#logindialog").keypress(function(e) {
            if (e.keyCode == $.ui.keyCode.ENTER) {
              $(this).parent().find("button:eq(1)").trigger("click");
            }
          });
        } });
	$("#loadingdialog").dialog({ autoOpen: false, modal: true });
	$.post( "data.php", { action: "prelogin" }, prelogin );
});
</script>
</body>
</html>
