"""Απομαγνητοφώνηση με faster-whisper (τοπικά, χωρίς κόστος). Το καλεί ο PHP worker.

    python transcribe.py <audio_16k_mono.wav> <έξοδος.txt> [--model large-v3-turbo] [--device auto|cuda|cpu]

Γράφει στο αρχείο εξόδου κείμενο με χρονοσημάνσεις [mm:ss] και τυπώνει μία γραμμή JSON με τη γλώσσα.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import wave
from pathlib import Path

os.environ.setdefault("HF_HUB_DISABLE_SYMLINKS_WARNING", "1")


def add_nvidia_dll_dirs() -> None:
    """Αν υπάρχουν τα pip πακέτα nvidia-cublas/cudnn, κάνε ορατά τα DLL τους (Windows)."""
    if sys.platform != "win32":
        return
    for base in map(Path, sys.path):
        nv = base / "nvidia"
        if nv.is_dir():
            for bin_dir in nv.glob("*/bin"):
                try:
                    os.add_dll_directory(str(bin_dir))
                    os.environ["PATH"] = str(bin_dir) + os.pathsep + os.environ.get("PATH", "")
                except OSError:
                    pass


def fmt_ts(sec: float) -> str:
    sec = int(sec)
    return f"{sec // 60:02d}:{sec % 60:02d}"


def load_pcm(path: str):
    import numpy as np

    with wave.open(path, "rb") as wf:
        pcm = np.frombuffer(wf.readframes(wf.getnframes()), dtype=np.int16)
    return pcm.astype(np.float32) / 32768.0


def run(audio: str, model_name: str, device: str) -> tuple[str, str]:
    from faster_whisper import WhisperModel

    model = WhisperModel(model_name, device=device, compute_type="float16" if device == "cuda" else "int8")
    segments, info = model.transcribe(load_pcm(audio), vad_filter=True, beam_size=5)
    lines = [f"[{fmt_ts(s.start)}] {s.text.strip()}" for s in segments if s.text.strip()]
    return "\n".join(lines), info.language


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("audio")
    ap.add_argument("out")
    ap.add_argument("--model", default="large-v3-turbo")
    ap.add_argument("--device", default="auto")
    args = ap.parse_args()

    add_nvidia_dll_dirs()
    devices = ["cuda", "cpu"] if args.device == "auto" else [args.device]
    last: Exception | None = None
    for dev in devices:
        try:
            text, lang = run(args.audio, args.model, dev)
            Path(args.out).write_text(text, encoding="utf-8")
            print(json.dumps({"language": lang, "device": dev, "segments": len(text.splitlines())}))
            return 0
        except Exception as exc:  # λείπουν CUDA DLL κ.λπ. — δοκιμή σε CPU
            last = exc
    print(str(last), file=sys.stderr)
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
