<?php
$memCache = new Memcached();
$memCache->addServer("127.0.0.1", 11211);
$mac0 = $memCache->get("mac0");
$volumeInc = 2;
$ddcutilArgs = "--bus 3";
if ($_SERVER["REQUEST_METHOD"] == "GET"){
	$brightnessValue = $memCache->get("monitorBrightness");
	if ($brightnessValue === false)
	{
	        $brightnessValue=shell_exec('ddcutil ' . $ddcutilArgs . ' getvcp --terse 10 | cut -d" " -f4');
	        $memCache->set("monitorBrightness", $brightnessValue);
	}
	$volumeValue = $memCache->get("monitorVolume");
	if ($volumeValue === false)
	{
	        $volumeValue=hexdec('0' . shell_exec('ddcutil ' . $ddcutilArgs . ' getvcp --terse 62 | cut -d" " -f7'));
	        $memCache->set("monitorVolume", $volumeValue);
	}
	if (!$mac0)
	{
	        $mac0 = rtrim(file_get_contents('macs/mac0.txt'));
	        $memCache->set("mac0", $mac0);
	}
	$r1=shell_exec('cat /sys/class/gpio/gpio5/value 2>&1');
	if ($r1 == "0\n")
	{
		$r1 = "checked";
	} else {
		$r1 = "";
	}
        $r2=shell_exec('cat /sys/class/gpio/gpio6/value 2>&1');
	$monitorControl="";
        if ($r2 == "0\n")
        {
                $r2 = " checked";
        } else {
                $r2 = "";
		$monitorControl = ' style="display: none"';
        }
        $ai=shell_exec('cat /sys/class/gpio/gpio24/value 2>&1');
	$ai1="";
	$ai2="";
        if ($ai == "0\n")
        {
                $ai1 = " checked";
        } else {
                $ai2 = " checked";
        }
	/*
	$ao=shell_exec('cat /sys/class/gpio/gpio23/value 2>&1');
	$ao1="";
	$ao2="";
	if ($ao == "0\n")
	{
		$ao1 = "checked";
	} else {
		$ao2 = "checked";
	}
	*/
	/*$tv=shell_exec('tvservice -s | cut -d" " -f2');
	if ($tv!="0x2\n")
	{
		$tv = "checked";
	}
	*/
	unset($memCache);
}
if ($_SERVER["REQUEST_METHOD"] == "POST"){
	$action = $_POST['realAction'];
	switch ($action)
	{
		case 1:
	                $volumeValue=$_POST['volume'];
                	$memCache->set("monitorVolume", $volumeValue);
			$memCache->set("homePiCommand", 'ddcutil ' . $ddcutilArgs . ' setvcp 62 '. $volumeValue . ' > /dev/null 2>/dev/null &');
			break;
		case 2:
			$brightnessValue=$_POST['brightness'];
	        	$memCache->set("monitorBrightness", $brightnessValue);
			$memCache->set("homePiCommand", 'ddcutil ' . $ddcutilArgs . ' setvcp 10 ' . $brightnessValue . ' > /dev/null 2>/dev/null &');
			break;
	}
	$inputValue=$_POST['source'];
	if (isset($inputValue))
	{
		$memCache->set("homePiCommand", 'ddcutil ' . $ddcutilArgs . ' setvcp 60 '.$inputValue . ' > /dev/null 2>/dev/null &');
	}
	$switchCmd=$_POST['switchcmd'];
	if (isset($switchCmd))
	{
		//$memCache->set("homePiCommand", 'irsend SEND_ONCE Delock ' . $switchCmd . ' > /dev/null 2>/dev/null &');
		$memCache->set("homePiCommand", "echo SEND_ONCE Delock " . $switchCmd . " | nc localhost 8701 -q 1 > /dev/null 2>/dev/null &");
	}
	$smLight=$_POST['smLight'];
	if (isset($smLight))
	{
		$memCache->set("homePiCommand", "for i in `seq 1 3`; do echo SEND_ONCE Osram " . $smLight . " | nc localhost 8701 -q 1 > /dev/null 2>/dev/null; done &");
	}
	$roomLight=$_POST['roomLight'];
	if (isset($roomLight))
	{
		$sfin = 3;
		if ($roomLight == "brup" || $roomLight == "brdown")
		{
			$sfin = 1;
		}
		$memCache->set("homePiCommand", "for i in `seq 1 " . $sfin . "`; do echo SEND_ONCE Osram " . $roomLight . " | nc localhost 8700 -q 1 > /dev/null 2>/dev/null; done &");
	}
	$wake = $_POST['wake'];
	if (isset($wake))
	{
                shell_exec('echo 6 > /sys/class/gpio/export; echo out > /sys/class/gpio/gpio6/direction; echo 0 > /sys/class/gpio/gpio6/value');
		$memCache->set("homePiCommand", 'ddcutil ' . $ddcutilArgs . ' setvcp 60 '.$inputValue . ' > /dev/null 2>/dev/null &');
		shell_exec('etherwake -i eth0 ' . $mac0 . ' > /dev/null 2>/dev/null &');
	}
	$bttx = $_POST['bttx'];
	if (isset($bttx))
	{
		$memCache->set("homePiCommand", 'echo 17 > /sys/class/gpio/export; echo out > /sys/class/gpio/gpio17/direction; echo 1 > /sys/class/gpio/gpio17/value && sleep 2 && echo 0 > /sys/class/gpio/gpio17/value');
	}
	$nok = $_POST['nok'];
	if (isset($nok))
	{
		//$memCache->set("homePiCommand", 'echo 23 > /sys/class/gpio/export; echo 24 > /sys/class/gpio/export; echo out > /sys/class/gpio/gpio23/direction; echo out > /sys/class/gpio/gpio24/direction; echo 0 > /sys/class/gpio/gpio23/value && echo 0 > /sys/class/gpio/gpio24/value && sleep 1 && echo 1 > /sys/class/gpio/gpio23/value && echo 1 > /sys/class/gpio/gpio24/value');
		$memCache->set("homePiCommand", 'echo 23 > /sys/class/gpio/export; echo out > /sys/class/gpio/gpio23/direction; echo 0 > /sys/class/gpio/gpio23/value && sleep 1 && echo 1 > /sys/class/gpio/gpio23/value');
	}
	/*$tvservice = $_POST['tvservice'];
	if (isset($tvservice))
	{
		$tv=shell_exec('tvservice -s | cut -d" " -f2');
		if ($tv="0x2\n")
		{
			$memCache->set("homePiCommand", 'tvservice -p &');
		} else {
			$memCache->set("homePiCommand", 'tvservice -o &');
		}
	}
	*/
	$r1 = $_POST['r1'];
	if (isset($r1))
	{
		// cannot use async daemon as there is a race between the value being set and it being read when GETting the web page further down
                shell_exec('echo 5 > /sys/class/gpio/export; echo out > /sys/class/gpio/gpio5/direction; echo ' . $r1 . ' > /sys/class/gpio/gpio5/value');
	}
        $r2 = $_POST['r2'];
        if (isset($r2))
        {
                shell_exec('echo 6 > /sys/class/gpio/export; echo out > /sys/class/gpio/gpio6/direction; echo ' . $r2 . ' > /sys/class/gpio/gpio6/value');
        }
        $ai = $_POST['ai'];
        if (isset($ai))
        {
                shell_exec('echo 24 > /sys/class/gpio/export; echo out > /sys/class/gpio/gpio24/direction; echo ' . $ai . ' > /sys/class/gpio/gpio24/value');
        }
	/*
        $ao = $_POST['ao'];
        if (isset($ao))
        {
                shell_exec('echo 23 > /sys/class/gpio/export; echo out > /sys/class/gpio/gpio23/direction; echo ' . $ao . ' > /sys/class/gpio/gpio23/value');
        }
	*/
	unset($memCache);
	header('Location: http://' . gethostname() . '.local/');
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>homepi</title>
<style>
.cssradio input[type="radio"]{display: none;}
.cssradio input[type="checkbox"]{display: none;}
.cssradio label{padding: 10px; margin: 15px 10px 0 0;
background: rgba(255, 255, 255, 0.8); border-radius: 3px;
display: inline-block; color: black;
cursor: pointer; border: 1px solid #000; width: 45%}
.cssradio input:checked + label {
background: green; font-weight:bold; color: white; border-color: green;}

/* Enables dark mode support in Safari */
:root {
    color-scheme: light dark;
}

* {
  box-sizing: border-box;
  font-family: "Helvetica Neue", Helvetica Neue, serif;
}

.slidercontainer {
  width: 90%; /* Width of the outside container */
}
/* The slider itself */
.slider {
  -webkit-appearance: none;
  width: 91%;
  height: 15px;
  border-radius: 5px;  
  background: #d3d3d3;
  outline: none;
  opacity: 0.7;
  -webkit-transition: .2s;
  transition: opacity .2s;
}

.slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 25px;
  height: 25px;
  border-radius: 50%; 
  background: #4CAF50;
  cursor: pointer;
}

.slider::-moz-range-thumb {
  width: 25px;
  height: 25px;
  border-radius: 50%;
  background: #4CAF50;
  cursor: pointer;
}

.container {
  border-radius: 5px;
  background-color: rgba(0, 0, 0, 0.1);/*#f2f2f2;*/
  padding: 20px;
}

.col-25 {
  float: left;
  width: 25%;
  margin-top: 6px;
}

.col-75 {
  float: left;
  width: 75%;
  margin-top: 6px;
}

/* Clear floats after the columns */
.row:after {
  content: "";
  display: table;
  clear: both;
}

.heading {
//  padding-top: 14px;
//  padding-bottom: 75px;
  position: relative;
}

.widthel {
  width: 750px;
}

@media screen and (max-width: 700px) {
  .col-25, .col-75, input[type=submit] {
    width: 100%;
    margin-top: 0;
  }
  .copyright {
    text-align: center;
  }
  .container, .heading, .widthel {
    width: 100%;
  }
}

.iconAnchor {
  text-decoration: none;
}

.iconImg {
  margin-top: 10px;
  margin-right: 15px;
  border-radius: 50%;
  padding-top: 4px;
  border: 1px solid black;
  width: 40px;
  height: 40px;
}
</style>
</head>
<body>
<div class="container widthel">
<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method=post>
	<h2>homepi</h2>
	<input name="realAction" style="display: none" value="0">
        <div class="cssradio">
                Lightbulb<br>
                <input onclick="this.form.submit()" type="radio" id="roomOn" name="roomLight" value="on"><label for="roomOn">On</label>
                <input onclick="this.form.submit()" type="radio" id="roomOff" name="roomLight" value="off"><label for="roomOff">Off</label>
                <input onclick="this.form.submit()" type="radio" id="roomBrUp" name="roomLight" value="brup"><label for="roomBrUp">Br. Up</label>
                <input onclick="this.form.submit()" type="radio" id="roomBrDown" name="roomLight" value="brdown"><label for="roomBrDown">Br. Down</label>
                <input onclick="this.form.submit()" type="radio" id="roomWhite" name="roomLight" value="white"><label for="roomWhite">Normal</label>
                <input onclick="this.form.submit()" type="radio" id="roomEpilepsy" name="roomLight" value="epilepsy"><label for="roomEpilepsy">Epilepsy</label>
        </div>
	<br>
        <div class="cssradio">
                Night bulb<br>
                <input onclick="this.form.submit()" type="radio" id="smOn" name="smLight" value="on"><label for="smOn">On</label>
                <input onclick="this.form.submit()" type="radio" id="smOff" name="smLight" value="off"><label for="smOff">Off</label>
        </div>
        <div class="cssradio">
	<br>
        Control<br>
                <input onclick="if (!document.getElementById('r1').checked) { document.getElementById('r1').checked = true; document.getElementById('r1').value = '1'; document.forms[0].submit(); } else { document.forms[0].submit(); }" type="checkbox" id="r1" name="r1" value="0"<?php echo $r1; ?>><label for="r1">Desk lamp</label>
                <input onclick="if (!document.getElementById('r2').checked) { document.getElementById('r2').checked = true; document.getElementById('r2').value = '1'; document.forms[0].submit(); } else { document.forms[0].submit(); }" type="checkbox" id="r2" name="r2" value="0"<?php echo $r2; ?>><label for="r2">Monitor</label>
        </div>
	<div class="sliderContainer"<?php echo $monitorControl; ?>>
		<br>Volume:
		<output id="volumeValue"><?php echo $volumeValue; ?></output>
		<br><br>
		<input name="volume" type="range" min="0" max="100" value=<?php echo $volumeValue; ?> class="slider" id="volumeSlider" oninput="volumeValue.value = volumeSlider.value" onchange="realAction.value = 1; this.form.submit()">
		<a style="display: none" class="iconAnchor" href="#" onclick="document.getElementById('volumeSlider').value = 0; document.getElementById('volumeValue').value = 0; document.forms[0].submit();"><img class="iconImg" src="icons/mute.png"></a>
		<a style="display: none" class="iconAnchor" href="#" onclick="document.getElementById('volumeSlider').value = document.getElementById('volumeSlider').value * 1 - <?php echo $volumeInc; ?>; document.getElementById('volumeValue').value = document.getElementById('volumeValue').value * 1 - <?php echo $volumeInc; ?>; document.forms[0].submit();"><img class="iconImg" src="icons/minus.png"></a>
		<a style="display: none" class="iconAnchor" href="#" onclick="document.getElementById('volumeSlider').value = document.getElementById('volumeSlider').value * 1 + <?php echo $volumeInc; ?>; document.getElementById('volumeValue').value = document.getElementById('volumeValue').value * 1 + <?php echo $volumeInc; ?>; document.forms[0].submit();"><img class="iconImg" src="icons/plus.png"></a>
		<a style="display: none" class="iconAnchor" href="#" onclick="document.getElementById('volumeSlider').value = 100; document.getElementById('volumeValue').value = 100; document.forms[0].submit();"><img class="iconImg" src="icons/volume.png"></a>
	</div>
	<div class="sliderContainer"<?php echo $monitorControl; ?>>
		<br>Brightness: 
		<output id="brightnessValue"><?php echo $brightnessValue; ?></output>
		<br><br>
		<input name="brightness" type="range" min="0" max="100" value=<?php echo $brightnessValue; ?> class="slider" id="brightnessSlider" oninput="brightnessValue.value = brightnessSlider.value" onchange="realAction.value = 2; this.form.submit()">
	</div>
	<div class="cssradio" style="display: none">
		<br>Display<br>
		<input onclick="this.form.submit()" type="radio" id="Power" name="switchcmd" value="power"><label for="Power">Standby</label>
		<input onclick="this.form.submit()" type="radio" id="Ch1" name="switchcmd" value="ch1"><label for="Ch1">Raspberry Pi</label>
		<input onclick="this.form.submit()" type="radio" id="Ch2" name="switchcmd" value="ch2"><label for="Ch2">Chromecast</label>
		<input onclick="this.form.submit()" type="radio" id="Ch3" name="switchcmd" value="ch3"><label for="Ch3">Ch 3 (unused)</label>
		<input onclick="this.form.submit()" type="radio" id="Ch4" name="switchcmd" value="ch4"><label for="Ch4">Ch 4 (unused)</label>
		<input onclick="this.form.submit()" type="radio" id="Ch5" name="switchcmd" value="ch5"><label for="Ch5">PlayStation 3</label>
		<input onclick="this.form.submit()" type="radio" id="ChPrev" name="switchcmd" value="prev"><label for="ChPrev">Prev Channel</label>
		<input onclick="this.form.submit()" type="radio" id="ChNext" name="switchcmd" value="next"><label for="ChNext">Next Channel</label>
	<br>
	</div>
	<div class="cssradio"<?php echo $monitorControl; ?>>
	<br>Monitor Input<br>
		<input onclick="this.form.submit()" type="radio" id="VGA" name="source" value="1"><label for="VGA">VGA</label>
		<input onclick="this.form.submit()" type="radio" id="DVI" name="source" value="3"><label for="DVI">PlayStation</label>
		<input onclick="this.form.submit()" type="radio" id="DP" name="source" value="15"><label for="DP">ThinkPad</label>
		<input onclick="this.form.submit()" type="radio" id="HDMI" name="source" value="17"><label for="HDMI">Chromecast</label>
	</div>
	<br>
        <div class="cssradio">
        Audio<br>
                <input onclick="document.forms[0].submit()" type="radio" id="ai2" name="ai" value="1"<?php echo $ai2; ?>><label for="ai2">Input: Monitor</label>
                <input onclick="document.forms[0].submit()" type="radio" id="ai1" name="ai" value="0"<?php echo $ai1; ?>><label for="ai1">Input: Wireless</label>
                <input onclick="this.form.submit()" type="radio" id="nok" name="nok" value="0"><label for="nok">Speakers On / Off</label>
                <input onclick="this.form.submit()" type="radio" id="bttx" name="bttx" value="0"><label for="bttx">Bluetooth On / Off</label>
        </div>
        <div class="cssradio" style="display: none">
	<br>
        Audio Output<br>
                <input onclick="document.forms[0].submit()" type="radio" id="aii2" name="aii" value="1"<?php echo $ai2; ?>><label for="aii2">Headphone</label>
                <input onclick="document.forms[0].submit()" type="radio" id="aii1" name="aii" value="0"<?php echo $ai1; ?>><label for="aii1">Speakers</label>
        </div>
        <div class="cssradio" style="display: none">
	<br>
        Audio Input<br>
                <input onclick="document.forms[0].submit()" type="radio" id="ao2" name="ao" value="0"<?php echo $ao1; ?>><label for="ao2">Wireless</label>
                <input onclick="document.forms[0].submit()" type="radio" id="ao1" name="ao" value="1"<?php echo $ao2; ?>><label for="ao1">Monitor</label>
        </div>
	<br>
        <div class="cssradio">
                Wake<br/>
                <input onclick="this.form.submit()" type="radio" id="WakeThinkPad" name="wake" value="0"><label for="WakeThinkPad">ThinkPad</label>
        </div>
	<br>
        <div class="cssradio" style="display: none">
        Raspberry Pi<br>
                <input onclick="this.form.submit()" type="radio" id="tvservice" name="tvservice" value="0"<?php echo $tv; ?>><label for="tvservice">Screen On / Off</label>
        </div>
	<br>
</form>
</div>
</body>
</html>
