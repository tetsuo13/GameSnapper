#!/home1/neoanime/opt-python/bin/python
"""
Minifies JavaScript from many files to generate one file.

Currently uses Google Closure appspot API, which requires sending the
JavaScript code over HTTP to compile. YUI Compressor 2.4.3 adds ability to
compile more than one file (wildcard support). Use that when available.
"""

import httplib
import os
import urllib
import sys

BASE_DIR = os.sep + os.path.join("home1", "neoanime", "gamesnapper") + os.sep
JS_DIR = BASE_DIR + os.path.join("htdocs", "js") + os.sep
TARGET_JS_FILE = JS_DIR + "gs.js"
ALREADY_COMPILED_FILES = ["jquery.js", "swfobject.js"]

def get_compiled_js_files(js_dir, file_list):
    """Get contents of compiled JavaScript files."""
    return "\n".join([open(os.path.join(js_dir, f)).read().strip() for f in file_list])

def get_js_files_to_compile(js_dir, target_js_filename, skip_list):
    """Return a list of JavaScript filenames to be compiled."""

    files = []

    for f in os.listdir(js_dir):
        if (f != os.path.basename(target_js_filename) and
            f not in skip_list):
            files.append(os.path.join(js_dir, f))

    return files

def compile_js_files(js_dir, files, target_filename):
    """Returns compiled contents of all JavaScript files."""

    # Concatenate all JavaScript files into one.
    js_code = "\n".join([open(f).read() + "\n" for f in files])

    params = urllib.urlencode([
        ("js_code", js_code),
        ("compilation_level", "SIMPLE_OPTIMIZATIONS"),
        ("output_format", "text"),
        ("output_info", "compiled_code"),
    ])

    headers = {"Content-type": "application/x-www-form-urlencoded"}
    conn = httplib.HTTPConnection("closure-compiler.appspot.com")
    conn.request("POST", "/compile", params, headers)
    response = conn.getresponse()
    data = response.read()
    conn.close
    return data

def write_compiled(js_code, target_file):
    """Writes the compiled code to target filename."""

    f = open(target_file, "w")
    f.write(js_code)
    f.close()

if __name__ == "__main__":
    compiled_js = get_compiled_js_files(JS_DIR, ALREADY_COMPILED_FILES)
    js_files = get_js_files_to_compile(JS_DIR, TARGET_JS_FILE, ALREADY_COMPILED_FILES)

    if js_files:
        compiled_js += "\n" + compile_js_files(JS_DIR, js_files, TARGET_JS_FILE)

    write_compiled(compiled_js, TARGET_JS_FILE)
