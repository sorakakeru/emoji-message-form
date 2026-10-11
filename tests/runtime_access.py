"""一時コピーを使いApacheで保存領域への直接アクセス拒否を検証します"""

import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request


SOURCE = Path(__file__).resolve().parents[1] / "src"
PHP = shutil.which("php")
HTTPD = shutil.which("httpd")
MODULES = Path("/usr/libexec/apache2")


def exercise(base):
    base = base.resolve()
    project = base / "public"
    functions = project / "_modules/fnc_inc/functions.php"
    functions.parent.mkdir(parents=True)
    shutil.copyfile(SOURCE / "_modules/fnc_inc/functions.php", functions)
    prepared_runtime = project / "_runtime"
    prepared_runtime.mkdir(mode=0o700)
    (prepared_runtime / ".htaccess").write_text("Require all denied\n")
    runtime = Path(subprocess.check_output(
        [PHP, "-r", "require $argv[1]; echo securityRuntimeDirectory();", str(functions)],
        text=True,
    ))
    assert runtime == project / "_runtime"
    (project / "probe.txt").write_text("public-ok")
    for name in ["login.json", "submission.json", "example.lock", ".pending.tmp"]:
        (runtime / name).write_text("private-marker")
    (runtime / "nested").mkdir()
    (runtime / "nested/probe.txt").write_text("private-marker")
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        port = listener.getsockname()[1]
    configuration = base / "httpd.conf"
    configuration.write_text(f'''ServerRoot "{base}"
Listen 127.0.0.1:{port}
ServerName 127.0.0.1
PidFile "{base / 'httpd.pid'}"
ErrorLog "{base / 'error.log'}"
LoadModule mpm_event_module "{MODULES / 'mod_mpm_event.so'}"
LoadModule unixd_module "{MODULES / 'mod_unixd.so'}"
LoadModule authz_core_module "{MODULES / 'mod_authz_core.so'}"
User #{os.getuid()}
Group #{os.getgid()}
DocumentRoot "{project}"
<Directory "{project}">
  AllowOverride AuthConfig
  Require all granted
</Directory>
''')
    subprocess.run([HTTPD, "-t", "-f", str(configuration)], check=True, capture_output=True)
    with (base / "server.log").open("w+") as log:
        server = subprocess.Popen([HTTPD, "-f", str(configuration), "-DFOREGROUND"],
                                  stdout=log, stderr=log)
        address = f"http://127.0.0.1:{port}/"
        client = urllib.request.build_opener(urllib.request.ProxyHandler({}))

        def request(path, method="GET"):
            try:
                response = client.open(urllib.request.Request(address + path, method=method), timeout=5)
            except urllib.error.HTTPError as error:
                response = error
            with response:
                return response.status, response.read()

        try:
            deadline = time.monotonic() + 10
            while True:
                try:
                    assert request("probe.txt") == (200, b"public-ok")
                    break
                except urllib.error.URLError:
                    if server.poll() is not None or time.monotonic() >= deadline:
                        log.seek(0)
                        raise RuntimeError("Apache failed to start: " + log.read())
                    time.sleep(0.05)
            checks = 1
            for name in ["", "login.json", "submission.json", "example.lock", ".pending.tmp",
                         ".htaccess", "nested/probe.txt"]:
                for method in ["GET", "HEAD"]:
                    status, body = request("_runtime/" + name, method)
                    assert status == 403 and b"private-marker" not in body, (name, method, status)
                    checks += 1
            rules = runtime / ".htaccess"
            saved_rules = base / "saved-rules"
            rules.rename(saved_rules)
            try:
                assert request("_runtime/submission.json") == (200, b"private-marker")
                checks += 1
            finally:
                saved_rules.rename(rules)
            assert request("_runtime/submission.json")[0] == 403
            checks += 1
            print(f"{checks} Apache access checks passed")
        finally:
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait()


if __name__ == "__main__":
    if not PHP or not HTTPD or not MODULES.is_dir():
        raise SystemExit("This test requires PHP and the macOS Apache installation")
    with tempfile.TemporaryDirectory(prefix="emoji-message-form-apache-tests-") as temporary:
        exercise(Path(temporary))
