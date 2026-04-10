#!/usr/bin/env python3
"""
server.py — dev server lokal untuk Mafatihul Akhyar
Menggantikan data-api.php saat development di komputer (tanpa PHP).
Di hosting/produksi tetap pakai data-api.php.

Jalankan:  python server.py
Buka:      http://localhost:5500
"""

import json
import os
import re
import threading
from http.server import HTTPServer, SimpleHTTPRequestHandler
from urllib.parse import urlparse

PORT = 5500
ROOT = os.path.dirname(os.path.abspath(__file__))
DATA_FILE = os.path.join(ROOT, "data.json")
lock = threading.Lock()


def read_data():
    with open(DATA_FILE, "r", encoding="utf-8") as f:
        raw = json.load(f)
    return raw.get("data", []) if isinstance(raw, dict) else raw


def write_data(arr):
    payload = json.dumps({"data": arr}, ensure_ascii=False, indent=2)
    with open(DATA_FILE, "w", encoding="utf-8") as f:
        f.write(payload)


def clean_id(raw_id: str) -> str:
    cleaned = re.sub(r"[^a-zA-Z0-9\-_]", "-", raw_id)
    cleaned = re.sub(r"-{2,}", "-", cleaned)
    return cleaned.strip("-")


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=ROOT, **kwargs)

    # Sembunyikan log request agar terminal bersih
    def log_message(self, fmt, *args):
        pass

    def send_json(self, code: int, body: dict):
        data = json.dumps(body, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(data)))
        self.send_header("Access-Control-Allow-Origin", "*")
        self.end_headers()
        self.wfile.write(data)

    # ── intercept /data-api.php ──────────────────────────────────────────────
    def do_OPTIONS(self):
        self.send_response(204)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.end_headers()

    def do_GET(self):
        path = urlparse(self.path).path.rstrip("/")
        if path in ("/data-api.php", "/data-api"):
            try:
                arr = read_data()
                self.send_json(200, {"ok": True, "data": arr})
            except Exception as e:
                self.send_json(500, {"ok": False, "error": str(e)})
            return
        # semua selain itu → serve file statis
        super().do_GET()

    def do_POST(self):
        path = urlparse(self.path).path.rstrip("/")
        if path not in ("/data-api.php", "/data-api"):
            self.send_json(404, {"ok": False, "error": "Not found"})
            return

        length = int(self.headers.get("Content-Length", 0))
        try:
            body = json.loads(self.rfile.read(length))
        except Exception:
            self.send_json(400, {"ok": False, "error": "Body JSON tidak valid"})
            return

        action = body.get("action", "")

        # ── TAMBAH ────────────────────────────────────────────────────────────
        if action == "add":
            raw_id   = str(body.get("id", "")).strip()
            judul    = str(body.get("judul", "")).strip()
            kategori = str(body.get("kategori", "")).strip()
            arab     = str(body.get("arab", "")).strip()

            if not raw_id or not judul or not arab:
                self.send_json(400, {"ok": False, "error": "ID, judul, dan teks doa wajib diisi"})
                return

            entry_id = clean_id(raw_id)
            if not entry_id:
                self.send_json(400, {"ok": False, "error": "ID tidak valid"})
                return

            with lock:
                arr = read_data()
                if any(d.get("id") == entry_id for d in arr):
                    self.send_json(409, {"ok": False, "error": "ID sudah ada, gunakan ID lain"})
                    return
                arr.append({
                    "id": entry_id,
                    "judul": judul,
                    "kategori": kategori,
                    "arab": arab,
                    "aktif": "TRUE",
                })
                write_data(arr)

            self.send_json(200, {"ok": True, "message": "Berhasil ditambah"})
            return

        # ── EDIT ──────────────────────────────────────────────────────────────
        if action == "update":
            entry_id = str(body.get("id", "")).strip()
            judul    = str(body.get("judul", "")).strip()
            kategori = str(body.get("kategori", "")).strip()
            arab     = str(body.get("arab", "")).strip()

            if not entry_id or not judul or not arab:
                self.send_json(400, {"ok": False, "error": "ID, judul, dan teks doa wajib diisi"})
                return

            with lock:
                arr = read_data()
                found = False
                for d in arr:
                    if d.get("id") == entry_id:
                        d["judul"]    = judul
                        d["kategori"] = kategori
                        d["arab"]     = arab
                        found = True
                        break
                if not found:
                    self.send_json(404, {"ok": False, "error": "ID tidak ditemukan"})
                    return
                write_data(arr)

            self.send_json(200, {"ok": True, "message": "Berhasil diupdate"})
            return

        # ── HAPUS ─────────────────────────────────────────────────────────────
        if action == "delete":
            entry_id = str(body.get("id", "")).strip()
            if not entry_id:
                self.send_json(400, {"ok": False, "error": "ID wajib diisi"})
                return

            with lock:
                arr = read_data()
                filtered = [d for d in arr if d.get("id") != entry_id]
                if len(filtered) == len(arr):
                    self.send_json(404, {"ok": False, "error": "ID tidak ditemukan"})
                    return
                write_data(filtered)

            self.send_json(200, {"ok": True, "message": "Berhasil dihapus"})
            return

        self.send_json(400, {"ok": False, "error": "Action tidak dikenal"})


if __name__ == "__main__":
    server = HTTPServer(("", PORT), Handler)
    print(f"Server berjalan di  http://localhost:{PORT}")
    print(f"Admin panel         http://localhost:{PORT}/admin.html")
    print(f"Tekan Ctrl+C untuk berhenti.\n")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nServer dihentikan.")
