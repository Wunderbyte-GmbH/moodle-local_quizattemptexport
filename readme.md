# Quizattemptexport

Automatically exports quiz attempts submitted by students into PDF files.

The files can be exported in two ways:
* Into the moodle file system, where they may be viewed/downloaded through the browser
* Into a directory within moodledata

To avoid performance impact when a large number of quiz attempts is submitted at the same time, the automatic export will be done by a scheduled task in the background.

You may also manually export any quiz attempt within the system, using the user interface hooked into the quiz instance administration. This is also where you may view and download the files from your browser.


> **Note:** This version supports **Debian 12 Bookworm (arm64)** architecture!

---

## 🧭 How to Check if You Need This Branch

Run the following commands on your system:

1. Check your system architecture:

```bash
uname -m
```

✅ Output should be: aarch64

2. Verify your OS version:

```bash
cat /etc/os-release
```
✅ Expected output should include something like:
```
PRETTY_NAME="Debian GNU/Linux 12 (bookworm)"
NAME="Debian GNU/Linux"
VERSION_ID="12"
VERSION="12 (bookworm)"
VERSION_CODENAME=bookworm
ID=debian
...
```


📦 Packaged Binary
This plugin uses a precompiled binary to perform PDF transformations, which might require installing some additional shared libraries.

You will need to install wkhtmltopdf for Debian 12 Bookworm (arm64).

🔗 Download the package from the [official wkhtmltopdf GitHub releases](https://github.com/wkhtmltopdf/packaging/releases/) and look for:

`wkhtmltox_0.12.6.1-3.bookworm_arm64.deb`

Install it using:
```bash
sudo apt install ./wkhtmltox_0.12.6.1-3.bookworm_arm64.deb
```
After installation, the binary should be available at:

/usr/local/bin/wkhtmltopdf

If not, you may need to manually move it to that path.

📁 Binary Path `/usr/local/bin/wkhtmltopdf`


You can also directly download version 0.12.6.1 r3 of wkhtmltopdf for Debian 12 Bookworm (arm64) from this [link](https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_arm64.deb).