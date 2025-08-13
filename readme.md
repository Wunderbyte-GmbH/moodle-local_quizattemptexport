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

---

## 🔎 How the **wkhtmltopdf** Binary Is Chosen (Locator)

The plugin determines which binary to run using this order:

1. **Plugin setting (override)**
   If the admin setting **“wkhtmltopdf binary path”** is set **and** the file is executable and responds to `--version`, the plugin uses **that** path.

2. **Bundled binary (per-platform)**
   Otherwise, the plugin detects the current platform (e.g. `linux-arm64`, `linux-x64`, `windows-x64`, …) and looks for a bundled binary at:

   ```
   local/quizattemptexport/bin/{platform}/wkhtmltopdf[.exe]
   ```

   If the file exists and is executable, that binary is used.

3. **System installation (PATH)**
   Otherwise, the plugin searches common locations and the system `PATH` for `wkhtmltopdf` and uses it if found.

4. **Error**
   If none of the above succeed, the plugin throws a localized error indicating that `wkhtmltopdf` could not be found.

This behavior is implemented in `wkhtmltopdf_locator::get_binary()`.

---

## 🔍 Diagnostics Page

An admin-only diagnostics page is available at:

```
/local/quizattemptexport/diagnostics.php
```

It shows:

* Detected platform (`os`, `arch`, `platform`, `ext`)
* Whether a custom path is set in settings and if it is executable
* Bundled binary candidate for the current platform
* Binary found in the system `PATH`
* The **effective** binary selected by the locator
* `wkhtmltopdf --version` outputs, and any resolution errors

Only users with the capability `moodle/site:config` (site administrators) can view this page.

---

## 📁 Bundled Binary Layout (Optional)

If you choose to ship binaries with the plugin, place them like this:

```
local/quizattemptexport/bin/
  linux-x64/      wkhtmltopdf
  linux-arm64/    wkhtmltopdf
  linux-armhf/    wkhtmltopdf
  linux-ia32/     wkhtmltopdf
  macos-x64/      wkhtmltopdf
  windows-x64/    wkhtmltopdf.exe
  windows-ia32/   wkhtmltopdf.exe
```

> On Linux/macOS, ensure the files are executable (`chmod 0755`).
> If you bundle additional `.so` libraries (from a generic tarball), place them in `bin/{platform}/lib/` and use a small wrapper script that sets `LD_LIBRARY_PATH` before executing the real binary.

---

## 🔐 Security Note

If you keep binaries inside the plugin directory, they may be publicly downloadable via the web server. Consider either:

* Storing binaries under **`$CFG->dataroot/quizattemptexport/bin/…`** (preferred, not web-accessible), or
* Blocking HTTP access to `local/quizattemptexport/bin/` via web server rules (`.htaccess`/Nginx).