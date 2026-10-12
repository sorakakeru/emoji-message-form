"""実際のログや認証情報を使わずHTTP経由のセッションを検証します"""

import http.cookiejar
import json
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


SOURCE = Path(__file__).resolve().parents[1] / "src"
PHP = shutil.which("php")
checks = 0


def check(condition, description):
    global checks
    assert condition, description
    checks += 1


def exercise(base, relative_path, secure_override=None):
    public = base / "public"
    project = public / relative_path
    project.mkdir(parents=True)
    for name in ["index.php", "admin/index.php", "_modules/fnc_inc/config.php",
                 "_modules/fnc_inc/functions.php", "_modules/tmpl/base.html.twig",
                 "_modules/tmpl/index.html.twig", "_modules/tmpl/form.html.twig",
                 "_modules/tmpl/admin/index.html.twig"]:
        destination = project / name
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(SOURCE / name, destination)
    (project / "_modules/vendor").symlink_to(SOURCE / "_modules/vendor", target_is_directory=True)
    (project / "log.json").write_text("[]")
    password_hash = subprocess.check_output(
        [PHP, "-r", "echo password_hash('dummy-password', PASSWORD_BCRYPT, ['cost'=>4]);"],
        text=True,
    )
    (project / ".env").write_text("ADMIN_PW=" + password_hash + "\n")
    config = project / "_modules/fnc_inc/config.php"
    if secure_override is not None:
        with config.open("a") as stream:
            stream.write("\n<?php $sessionSecure = " + ("true" if secure_override else "false") + ";\n")
    sessions = base / "sessions"
    sessions.mkdir()
    runtime = project / "_runtime"
    runtime.mkdir(mode=0o700)
    (runtime / ".htaccess").write_text("Require all denied\n")
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        port = listener.getsockname()[1]
    log = (base / "server.log").open("w+")
    server = subprocess.Popen(
        [PHP, "-d", "session.save_path=" + str(sessions), "-S", "127.0.0.1:" + str(port),
         "-t", str(public)], stdout=log, stderr=log,
    )
    address = "http://127.0.0.1:" + str(port) + "/" + (urllib.parse.quote(relative_path) + "/" if relative_path else "")
    jar = http.cookiejar.CookieJar()
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

    def request(path="", data=None):
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = client.open(address + path, data=body, timeout=5)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.headers, response.read().decode()

    def token(html):
        match = re.search(r'name="token" value="([a-f0-9]{64})"', html)
        check(match is not None, "Form has a CSRF token")
        return match.group(1)

    try:
        deadline = time.monotonic() + 5
        while True:
            if server.poll() is not None:
                log.seek(0)
                raise RuntimeError("HTTP test server failed: " + log.read())
            try:
                status, headers, html = request()
                break
            except urllib.error.URLError:
                if time.monotonic() >= deadline:
                    raise
                time.sleep(0.02)
        check(status == 200, "Initial form opens")
        cookie = headers.get("Set-Cookie", "")
        check("HttpOnly" in cookie and "SameSite=Lax" in cookie, "Cookie retains browser protections")
        check(headers.get("Cache-Control") == "no-store", "Form cannot be cached")
        if secure_override is True:
            check("secure" in cookie.lower(), "Explicit production setting keeps Secure on HTTP")
            return
        check("secure" not in cookie.lower(), "Loopback HTTP cookie can be returned by the client")
        expected_path = urllib.parse.urlsplit(address).path
        check("path=" + expected_path in cookie, "Cookie path follows the actual URL")
        form_token = token(html)
        status, _, html = request(data={"send": "", "token": form_token,
                                       "emoji": "emoji0", "message": "first"})
        check(status == 200 and "送信しました、ありがとうございます！" in html, "GET -> POST with browser cookies succeeds")
        check(len(json.loads((project / "log.json").read_text())) == 1, "Submission is stored once")
        check((runtime / "submission.json").is_file(), "HTTP submission stores its counter inside the application")
        check((runtime / ".htaccess").read_text() == "Require all denied\n", "Runtime directory has a web access denial rule")
        form_token = token(html)
        status, _, admin = request("admin/")
        check(status == 200, "Admin form opens under the same cookie path")
        admin_token = token(admin)
        status, _, admin = request("admin/", {"send": "", "token": admin_token,
                                               "password": "dummy-password"})
        check(status == 200 and "first" in admin, "Login succeeds through HTTP")
        status, _, admin = request("admin/")
        check("first" in admin and "ログアウト" in admin, "Admin session persists to another request")
        status, _, html = request(data={"send": "", "token": form_token,
                                       "emoji": "emoji0", "message": "second"})
        check(status == 200 and "送信しました、ありがとうございます！" in html, "Login ID rotation preserves the public form token")
        admin_token = token(admin)
        status, _, html = request("admin/", {"logout": "", "token": admin_token})
        check(status == 200 and "login password" in html and "second" not in html,
              "Logout redirects and removes authentication")
        status, _, html = request()
        status, _, html = request(data={"send": "", "token": token(html),
                                       "emoji": "emoji0", "message": "third"})
        check(status == 200 and "送信しました、ありがとうございます！" in html, "A new anonymous session can submit after logout")
        before = (project / "log.json").read_bytes()
        status, _, _ = request(data={"send": "", "token": "wrong", "emoji": "emoji0"})
        check(status == 400 and (project / "log.json").read_bytes() == before, "Invalid CSRF remains rejected")
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait()
        log.close()
        if runtime.is_dir():
            shutil.rmtree(runtime)


if __name__ == "__main__":
    with tempfile.TemporaryDirectory(prefix="emoji-message-form-http-tests-") as temporary:
        root = Path(temporary)
        exercise(root / "standard", "emoji-message-form")
        exercise(root / "nested", "local/絵文字＆一言メッセージフォーム/emoji-message-form/src")
        exercise(root / "document-root", "")
        exercise(root / "forced-secure", "emoji-message-form", secure_override=True)
    print(str(checks) + " HTTP checks passed")
