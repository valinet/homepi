<?php
$mac0 = file_get_contents('macs/mac0.txt');
$volumeInc = 2;
$ddcutilArgs = "--bus 1";
if ($_SERVER["REQUEST_METHOD"] == "GET"){
	$brightnessValue=shell_exec('ddcutil ' . $ddcutilArgs . ' getvcp --terse 10 | cut -d" " -f4');
	$volumeValue=hexdec('0' . shell_exec('ddcutil ' . $ddcutilArgs . ' getvcp --terse 62 | cut -d" " -f7'));
//	$inputValue=trim(shell_exec('ddcutil ' . $ddcutilArgs . ' getvcp --terse 60 | cut -d" " -f4'));
//	echo $inputValue;
/* <?php echo (!strcmp($inputValue, 'x11') ? 'checked' : ''); ?> */
}
if ($_SERVER["REQUEST_METHOD"] == "POST"){
	//print_r($_POST);
	$brightnessValue=$_POST['brightness'];
	shell_exec('ddcutil ' . $ddcutilArgs . ' setvcp 10 '.$brightnessValue);
	$inputValue=$_POST['source'];
	shell_exec('ddcutil ' . $ddcutilArgs . ' setvcp 60 '.$inputValue);
	$volumeValue=$_POST['volume'];
	shell_exec('ddcutil ' . $ddcutilArgs . ' setvcp 62 '.$volumeValue);
	$hostname=substr(shell_exec('cat /etc/hostname'), 0, -1);
	$switchCmd=$_POST['switchcmd'];
//	if (empty($switchCmd) != 1)
//	{
		shell_exec('irsend SEND_ONCE Delock '.$switchCmd);
//		shell_exec('ddcutil setvcp 60 17');
//	}
	header('Location: http://' . $hostname . '.local/');
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Media Centre Control</title>
<style>
.cssradio input[type="radio"]{display: none;}
.cssradio label{padding: 10px; margin: 15px 10px 0 0;
background: #fff; border-radius: 3px;
display: inline-block; color: #000;
cursor: pointer; border: 1px solid #000; width: 45%}
.cssradio input:checked + label {
background: green; font-weight:bold; color: #fff; border-color: green;}

* {
  box-sizing: border-box;
//  font-family: "Arial", Arial, serif;
}

.slidercontainer {
  width: 100%; /* Width of the outside container */
}
/* The slider itself */
.slider {
  -webkit-appearance: none;
  width: 100%;
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
  background-color: #f2f2f2;
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
<div class="heading widthel">
	<h1>Media Centre Control</h1>
</div>
<div class="container widthel">
<form action="<?php echo shell_exec('etherwake -i eth0 $mac0'); ?>" method="post">
	<p>Wake:
		<input type="submit" value="ThinkPad">
	</p>
</form>
<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method=post>
	<p>Volume:</p>
	<div class="sliderContainer">
		<input name="volume" type="range" min="0" max="100" value=<?php echo $volumeValue; ?> class="slider" id="volumeSlider" oninput="volumeValue.value = volumeSlider.value" onchange="this.form.submit()">
		<output id="volumeValue"><?php echo $volumeValue; ?></output><br/>
		<a class="iconAnchor" href="#" onclick="document.getElementById('volumeSlider').value = 0; document.getElementById('volumeValue').value = 0; document.forms[0].submit();"><img class="iconImg" src="icons/mute.png"></a>
		<a class="iconAnchor" href="#" onclick="document.getElementById('volumeSlider').value = document.getElementById('volumeSlider').value * 1 - <?php echo $volumeInc; ?>; document.getElementById('volumeValue').value = document.getElementById('volumeValue').value * 1 - <?php echo $volumeInc; ?>; document.forms[0].submit();"><img class="iconImg" src="icons/minus.png"></a>
		<a class="iconAnchor" href="#" onclick="document.getElementById('volumeSlider').value = document.getElementById('volumeSlider').value * 1 + <?php echo $volumeInc; ?>; document.getElementById('volumeValue').value = document.getElementById('volumeValue').value * 1 + <?php echo $volumeInc; ?>; document.forms[0].submit();"><img class="iconImg" src="icons/plus.png"></a>
		<a class="iconAnchor" href="#" onclick="document.getElementById('volumeSlider').value = 100; document.getElementById('volumeValue').value = 100; document.forms[0].submit();"><img class="iconImg" src="icons/volume.png"></a>
	</div>
	<p>Adjust monitor brightness:</p>
	<div class="sliderContainer">
		<input name="brightness" type="range" min="1" max="100" value=<?php echo $brightnessValue; ?> class="slider" id="brightnessSlider" oninput="brightnessValue.value = brightnessSlider.value" onchange="this.form.submit()">
		<output id="brightnessValue"><?php echo $brightnessValue; ?></output>
	</div>
	<br>
	<div class="cssradio">
		Control HDMI switch:<br>
		<input onclick="this.form.submit()" type="radio" id="Power" name="switchcmd" value="power"><label for="Power">Standby</label>
		<input onclick="this.form.submit()" type="radio" id="Ch1" name="switchcmd" value="ch1"><label for="Ch1">Ext. HDMI</label>
		<input onclick="this.form.submit()" type="radio" id="Ch2" name="switchcmd" value="ch2"><label for="Ch2">Chromecast</label>
		<input onclick="this.form.submit()" type="radio" id="Ch3" name="switchcmd" value="ch3"><label for="Ch3">Ch 3 (unused)</label>
		<input onclick="this.form.submit()" type="radio" id="Ch4" name="switchcmd" value="ch4"><label for="Ch4">Ch 4 (unused)</label>
		<input onclick="this.form.submit()" type="radio" id="Ch5" name="switchcmd" value="ch5"><label for="Ch5">PlayStation 3</label>
		<input onclick="this.form.submit()" type="radio" id="ChPrev" name="switchcmd" value="prev"><label for="ChPrev">Prev Channel</label>
		<input onclick="this.form.submit()" type="radio" id="ChNext" name="switchcmd" value="next"><label for="ChNext">Next Channel</label>
	</div>
	<br>
	<div class="cssradio">
		Choose monitor input:<br>
		<input onclick="this.form.submit()" type="radio" id="VGA" name="source" value="1"><label for="VGA">VGA</label>
		<input onclick="this.form.submit()" type="radio" id="DVI" name="source" value="3"><label for="DVI">DVI</label>
		<input onclick="this.form.submit()" type="radio" id="DP" name="source" value="15"><label for="DP">DisplayPort</label>
		<input onclick="this.form.submit()" type="radio" id="HDMI" name="source" value="17"><label for="HDMI">HDMI</label>
	</div>
</form>
</div>
</body>
</html>
