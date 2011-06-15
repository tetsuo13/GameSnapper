#!/usr/bin/python
"""
Minifies CSS from many files to generate one file.
"""

import os
import subprocess
import sys

BASE_DIR = os.path.abspath(__file__ + "/../../")
CSS_DIR = BASE_DIR + os.sep + os.path.join("htdocs", "css") + os.sep
TARGET_CSS_FILE = CSS_DIR + "gs.css"

def get_compiled_js_files(js_dir, file_list):
    """Get contents of compiled JavaScript files."""
    return "\n".join([open(os.path.join(js_dir, f)).read().strip() for f in file_list])

def get_css_files_to_compile(css_dir, target_css_filename):
    """Return a list of CSS filenames to be compiled."""

    files = []

    for f in os.listdir(css_dir):
        if f != os.path.basename(target_css_filename):
            files.append(os.path.join(css_dir, f))

    return files

def compile_css_files(css_dir, files, target_filename):
    """Returns compiled contents of all CSS files."""

    data = ""

    for f in files:
        proc = subprocess.Popen(["java", "-jar", "yuicompressor.jar", "--type", "css", f], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        stdout, stderr = proc.communicate()
        if proc.returncode == 0:
            data += stdout
        else:
            print "Error in " + f + ":"
            print stderr

    return data

def write_compiled(code, target_file):
    """Writes the compiled code to target filename."""

    f = open(target_file, "w")
    f.write(code)
    f.close()

if __name__ == "__main__":
    css_files = get_css_files_to_compile(CSS_DIR, TARGET_CSS_FILE)

    if css_files:
        compiled_css = compile_css_files(CSS_DIR, css_files, TARGET_CSS_FILE)
        write_compiled(compiled_css, TARGET_CSS_FILE)
