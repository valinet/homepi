# homepi
**homepi** contains configuration data and front end files for the Raspberry Pi that automates my home office setup.

![Screenshot](/conf/homepi.png?raw=true "Screenshot")

Rant: I made the mistake of taking the screenshot using the "improved" Win+Ctrl+S crap instead of using trusty Snipping Tool. It seems that it cannot handle 3 monitors properly and snipped some more content on the left side. I leave it here as a testimony of the skill of some employees at Microsoft (not all, the WSL guys are brilliant, for example), and their managers, who do not have anything better to do with their time than to reinvent built-in tools and chnage OS icons every 6 months on a period of 10 years. Don't worry, I have rants prepared for other parties, as well.

## What does it do?

**homepi** allows me to:

* wake my ThinkPad laptop without reaching for the button on the docking station, using Wake on LAN
* adjust volume and brightness and change active source of my monitor's speakers, via Display Data Channel, using awesome [ddcutil](https://ddcutil.com); brightness and volume control come especially useful when watching movies or playing games at night on something like a PlayStation 3, which does not have a quick way of adjusting the volume, for example
* change source for my [Delock 4K HDMI 2.0 18685 switch](https://www.delock.com/produkt/18685/merkmale.html), by sending IR commands I captured from the remote using [LIRC](https://www.lirc.org/) (there are some more expensive, fancier switches that have a serial port, or cheaper ones which only have buttons so you'll have to resort to soldering some transistors on those)
* make my printer available on the LAN, which is a [Brother HL-1110](https://support.brother.com/g/b/downloadtop.aspx?c=eu_ot&lang=en&prod=hl1110_us_eu_as), using [CUPS](https://www.cups.org/)
* download and upload files quickly from and to the Pi
* in the past, it used to control my no-name AC unit as well, but I have since changed the place I live in and haven't merged the files; fortunately, the summer is coming and I will need the AC back so I will integrate that as well, probably; I still have the Arduino files, the IR remote used some lengthy codes which I had to figure their meaning, since the ATmega does not have enough memory to hardcode all the values - plus it would've been way more boring than actually "cracking" the codes

Rant: For me, automation does not mean stupid cloud connected voice assistants - it means creating shortcuts using efficient and straight forward ways without any unnecessary third parties or dependencies. I do not need for various assistants to listen to me just to change the damn monitor input, I can do it perfectly fine myself, way quicker by browsing to a site in Safari on my iPhone than waiting for the "AI" to figure what the hell I meant. Also, constantly not having these AIs in any other native language than English and a few others, despite text to speech existing for decades now, makes anyone talking to them look weird, and it becomes cumbersome so I'd rather not (fun fact, Siri was hacked to work in other languages, such as Romanian, [a long time ago](https://www.youtube.com/watch?v=6NWRbzZCHn8), yet Apple, due to some stupid reason, still keeps it closed and unavailable for a lot of user - same way it provides a suggestion bar on the keyboard only for 3 languages, while others keyboards implement multiple languages simultaneously).

## Configuration

**homepi** run as a simple web interface. To access it, you'll need a web server and a PHP interpreter. Initially, I used Apache2, but did not have time to bother with its crappy config files so I switched to nginx which took 5 minutes to configure, using the first Google hit (for some reason, Apache would not serve files in subdirectories, and I got fed up with attempting to fix it). A generic command would be:

```
sudo apt install nginx php-fpm
```

I modified the default nginx config to something like this:

```
        ...

​        \# Add index.php to the list if you are using PHP

​        index index.php index.html index.htm index.nginx-debian.html;

​        server_name homepi.local;

​        location / {
​                \# First attempt to serve request as file, then
​                \# as directory, then fall back to displaying a 404.
​                try_files $uri $uri/ $uri.html @extensionless-php;
​        }

​        \# access .php files directly
​        location @extensionless-php {
​                rewrite ^(.*)$ $1.php last;
​        }

​        \# pass PHP scripts to FastCGI server
​        \#
​        location ~ \.php$ {
​                include snippets/fastcgi-php.conf;

​                \# With php-fpm (or other unix sockets):
​                fastcgi_pass unix:/run/php/php7.3-fpm.sock;
​                \# With php-cgi (or other tcp sockets):
​                \#fastcgi_pass 127.0.0.1:9000;
​        }

​        \# deny access to .htaccess files, if Apache's document root
​        \# concurs with nginx's one
​        \#
​        location ~ /\.ht {
​                deny all;
​        }

​        ...
```

Also, replace the following line (as of php-7.3, line # is 793):

```
;cgi.fix_pathinfo=1
```

with:

```
cgi.fix_pathinfo=0
```

Now, regarding controlling the monitor via DDC, first install ddcutil:

```
sudo apt install ddcutil
```

For ddcutil to work, you need to enable i2c by adding the following line in */boot/config.txt*:

```
dtparam=i2c2_iknowwhatimdoing
```

Also, add yourself to i2c group, and also www-data, in order to run ddcutil without sudo (it needs access to i2c bus):

```
sudo usermod -aG i2c pi
sudo usermod -aG i2c www-data
```

Because my 4K monitor has a single HDMI port which is taken by the HDMI switch, and a single DisplayPort which is taken by the ThinkPad Ultra Dock, I am left with two other ports: VGA and DVI. Initially, the Pi was connected via an HDMI-to-DVI cable to DVI on the monitor, but since I needed that cable for adding some secondary monitors, I decided to migrate to controlling the monitor via the i2c bus in the VGA connector (also, it meant I no longer had to execute a service, at startup, that just run *tvservice -o* basically). I figured out which pins of the connector represent the 5V, GND, SCA, and SCL with the help of this resource: http://www.righto.com/2018/03/reading-vga-monitors-configuration-data.html. Then, I mapped those to appropiate pins on Raspberry Pi: pin 2 - 5V, pin 6 - GND, pin 3 - SDA, pin 5- SCL using jumper wires. Finally, enable i2c on RPi by adding the following to */boot/config.txt*:

```
dtparam=i2c_arm=on
```

I did not use level shifters because as far as I know, i2c devices only pull the line low, while it is kept high by the master, which in this case is RPi, which keeps at 3.3V as RPi is a 3.3V device. Monitors use 5V logic, but they should only pull the line down. Apparently, the monitor and RPi both can "understand" 3.3V as high, so I got away with it. It worked with my setup, it did not fry anything, but I think I may need more clarification about it, feel free to help me out.

To specify a different i2c bus when invoking ddcutil, pass the *--bus x* argument, where x is the number in something like */dev/i2c-1*. On my RPi, the bus is indeed 1. Test if ddcutil detects something with the following command:

```
ddcutil --bus 1 detect
```

For controlling the HDMI switch using infrared, you also need to install LIRC, which on Raspberry Pi 3 running Raspbian Buster is a bit involved and requires patching the library. Luckily, someone wrote some files to automate this, you can read about it here: https://www.raspberrypi.org/forums/viewtopic.php?f=28&t=235256. In short, the commands are along the lines of:

> ```
> sudo apt install dh-exec doxygen expect libasound2-dev libftdi1-dev libsystemd-dev libudev-dev libusb-1.0-0-dev libusb-dev man2html-base portaudio19-dev socat xsltproc python3-yaml dh-python libx11-dev python3-dev python3-setuptools
> mkdir build
> cd build
> apt source lirc
> wget https://raw.githubusercontent.com/neuralassembly/raspi/master/lirc-gpio-ir-0.10.patch
> patch -p0 -i lirc-gpio-ir-0.10.patch
> cd lirc-0.10.1
> debuild -uc -us -b
> cd ..
> sudo apt install ./liblirc0_0.10.1-5.2_armhf.deb ./liblircclient0_0.10.1-5.2_armhf.deb ./lirc_0.10.1-5.2_armhf.deb 
> ```

After doing that, create some configs for LIRC and copy the "delock.conf" file to its library:

```
sudo cp /etc/lirc/lirc_options.conf.dist /etc/lirc/lirc_options.conf
sudo cp /etc/lirc/lircd.conf.dist /etc/lirc/lircd.conf
sudo cp delock.conf /etc/lirc/lircd.conf.d/delock.conf
```

This should allow you to use an infrared LED controlled by a GPIO pin (I trigger mine though an NPN transistor with base connected to GPIO pin 22). For that, enable sending IR on pin 22 (BCM 25) by adding the following line in */boot/config.txt*:

```
dtoverlay=gpio-ir-tx,gpio_pin=22
```

To wake computers on the LAN, you can send a magic packet which triggers the built-in Wake on LAN functionality of the network card of the computer. That, of course, if you enable the appropiate support in the BIOS/UEFI of the system, and also in the driver (which sometimes overrides the firmware setting). On my Intel I219-V NIC, the relevant option that has to be enable is called *Wake on Magic Packet*. First, install etherwake:

```
sudo apt install etherwake
```

Then, one can use a command like the following to attempt to wake the computer (substitute aa:bb... with the MAC address of the NIC of the computer you are trying to wake, and eth0 should be fine for Rpi3, but use the appropriate one in your case):

```
sudo etherwake -i eth0 aa:bb:cc:dd:ee:ff
```

For a PHP file to be able to run this. instinct might tell you to NOPASSWD it for the www-data user in /etc/sudoers with visudo. Actually, there is a better way to make this work, as it needs sudo just for creating a certain socket type (a raw socket to be more precise, on a privileged port), otherwise it runs with regular privileges which minimizes the attack vector should the application contain a bug. Just run this one time and then you should be good to go:

```
sudo setcap 'CAP_NET_RAW+eip' $(which etherwake)
```

Of course, you can read more about capabilities on the always excellent go-to resource regarding Linux stuff, which is the incredible Arch Linux wiki: https://wiki.archlinux.org/index.php/Capabilities

For the provided web service to work with your MAC, put it in a file called *mac0.txt* in a folder *macs* created in the root of the web server.

Up to this point, all the functionality could be achieved, albeit with a bit more work, using a microcontroller like an Arduino as well. When I started doing this, I did not have the time to make it work on Arduino, even though I have experience and have done stuff like this in the past (so much fun serving web pages in chunks with the ENC28J60). As now I also have a non-networked printer, I am glad I chose the RPi way.

So, for my printer, of course, first step is to install CUPS:

```
sudo apt install cups
```

Then, edit file */etc/cups/cupsd.conf* and make two changes, as highlighted on the [HowToGeek](https://www.howtogeek.com/169679/how-to-add-a-printer-to-your-raspberry-pi-or-other-linux-computer/) article:

> Inside the file, look for this section:
>
> ```
> # Only listen for connections from the local machine
> Listen localhost:631
> ```
>
> Comment out the “Listen localhost:631” line and replace it with the following:
>
> ```
> # Only listen for connections from the local machine
> # Listen localhost:631
> Port 631
> ```
>
> This instructs CUPS to listen for any contact on any networking interface as long as it is directed at port 631.
>
> Scroll further down in the config file until you see the “location” sections. In the block below, we’ve bolded the lines you need to add to the config:
>
> ```
> < Location / >`
> `# Restrict access to the server...`
> `Order allow,deny`
> `**Allow @local**`
> `< /Location >`
> ``
> `< Location /admin >`
> `# Restrict access to the admin pages...`
> `Order allow,deny`
> `**Allow @local**`
> `< /Location >`
> ``
> `< Location /admin/conf >`
> `AuthType Default`
> `Require user @SYSTEM`
> ``
> `# Restrict access to the configuration files...`
> `Order allow,deny`
> `**Allow @local**`
> `< /Location >
> ```
>
> The addition of the “allow @local” line allows access to CUPS from any computer on your local network.

Then, to make your life easier, add your user to *lpadmin* group so that you can manage printers in the web interface as well:

```
sudo usermod -aG lpadmin pi
```

Also, I recommend installing the brlaser driver which works perfectly with the HL-1110:

```
sudo apt install printer-driver-brlaser
```

Finally, it is a matter of browsing to http://homepi.local:631 (or whatever your Pi's hostname is) and adding a new printer from the interface. Choose the printer connected via USB. For model, HL1110 is not available in the list, so I simply chose: "Brother DCP-1510 series, using brlaser (en)" and it worked.

That's it, I hope I did not miss major deal breakers. To access the web service, go to http://homepi.local (if mDNS discovery is supported by your OS - Rant & spoiler: Android, despite reaching version 11 this year, and being backed by Google which is too bothered to work on "really" important things, still does not support mDNS .local domains; "just" download Android Studio and create a web view app which just loads the web site in a damn web view, as there actually is an [mDNS API in Android](https://developer.android.com/training/connect-devices-wirelessly/nsd) which can discover your RPi domain - I did this for a previous project when I was daily driving an droid, yet again, I do not remember where I put the files - should I find them, I will upload the example at the earliest convinience; it is stupid, useless, but hey, I hate memorizing IPs, even though my Pi runs on one I used statically since forever, and Google really has to rearrange the icons in the notification area again this year so they ain't got time for "boring" stuff like that).

Thanks for bearing with me, hope you find this useful!

## License

A lot of the stuff in this repo is collected and based on bits from various sources, but being just snippets and pieces of information, I think releasing all of this under MIT license is fair.
