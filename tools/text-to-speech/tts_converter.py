#!/usr/bin/env python3
"""
Text-to-Speech converter.
Default engine: edge-tts (natural voices). Fallback: gTTS.

Usage:
  python tts_converter.py --catalog
  python tts_converter.py --catalog --only 01_dashboard_overview
  python tts_converter.py --file scripts/01_dashboard_overview.txt --out demo.mp3
  python tts_converter.py "Hello" --voice en-IN-NeerjaNeural
"""

from __future__ import annotations

import argparse
import asyncio
import json
import re
import sys
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parent
OUTPUT_DIR = ROOT / "output"
SCRIPTS_DIR = ROOT / "scripts"

DEFAULT_VOICE = "en-IN-NeerjaNeural"
WORDS_PER_MINUTE = 140


def parse_duration(value: str) -> int:
    value = (value or "").strip()
    if not value:
        return 0
    parts = value.split(":")
    if not all(p.isdigit() for p in parts):
        raise ValueError(f"Invalid duration: {value!r} (use M:SS)")
    nums = [int(p) for p in parts]
    if len(nums) == 1:
        return nums[0]
    if len(nums) == 2:
        return nums[0] * 60 + nums[1]
    if len(nums) == 3:
        return nums[0] * 3600 + nums[1] * 60 + nums[2]
    raise ValueError(f"Invalid duration: {value!r}")


def estimate_seconds(text: str) -> int:
    words = len(re.findall(r"\S+", text))
    return max(1, round(words / WORDS_PER_MINUTE * 60))


def format_seconds(total: int) -> str:
    m, s = divmod(max(0, total), 60)
    return f"{m}:{s:02d}"


def resolve_output_path(output_path: Path | None, tag: str) -> Path:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    if output_path is None:
        stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        return OUTPUT_DIR / f"tts_{tag}_{stamp}.mp3"
    path = Path(output_path)
    if not path.is_absolute():
        path = OUTPUT_DIR / path
    path.parent.mkdir(parents=True, exist_ok=True)
    return path


async def _edge_save(text: str, voice: str, output_path: Path) -> None:
    import edge_tts

    communicate = edge_tts.Communicate(text, voice)
    await communicate.save(str(output_path))


def convert_with_edge(text: str, *, voice: str, output_path: Path) -> Path:
    asyncio.run(_edge_save(text, voice, output_path))
    return output_path


def convert_with_gtts(text: str, *, lang: str, slow: bool, output_path: Path) -> Path:
    from gtts import gTTS

    gTTS(text=text, lang=lang, slow=slow).save(str(output_path))
    return output_path


def convert_text_to_speech(
    text: str,
    *,
    engine: str = "edge",
    voice: str = DEFAULT_VOICE,
    lang: str = "en",
    slow: bool = False,
    output_path: Path | None = None,
) -> Path:
    text = text.strip()
    if not text:
        raise ValueError("Text is empty. Nothing to convert.")

    out = resolve_output_path(output_path, voice.replace(" ", "_"))

    if engine == "edge":
        try:
            return convert_with_edge(text, voice=voice, output_path=out)
        except ImportError as exc:
            raise SystemExit(
                "edge-tts not installed. Run:\n  pip install -r requirements.txt"
            ) from exc

    try:
        return convert_with_gtts(text, lang=lang, slow=slow, output_path=out)
    except ImportError as exc:
        raise SystemExit(
            "gTTS not installed. Run:\n  pip install -r requirements.txt"
        ) from exc


def generate_from_catalog(
    catalog_path: Path,
    *,
    only_id: str | None = None,
    engine: str = "edge",
    voice: str = DEFAULT_VOICE,
) -> int:
    data = json.loads(catalog_path.read_text(encoding="utf-8"))
    if not isinstance(data, list):
        raise ValueError("catalog.json must be a JSON array")

    generated = 0
    for item in data:
        item_id = str(item.get("id", "")).strip()
        if only_id and item_id != only_id:
            continue

        title = item.get("title", item_id)
        lang = item.get("lang", "en")
        item_voice = item.get("voice", voice)
        script_name = item.get("script_file")
        if not script_name:
            print(f"Skip {title}: no script_file")
            continue

        script_path = Path(script_name)
        if not script_path.is_absolute():
            script_path = catalog_path.parent / script_path
        if not script_path.exists():
            print(f"Skip {title}: missing {script_path}")
            continue

        text = script_path.read_text(encoding="utf-8")
        target = parse_duration(str(item.get("target_duration", "")))
        estimated = estimate_seconds(text)

        if target:
            delta = estimated - target
            status = "OK" if abs(delta) <= 10 else ("LONG" if delta > 0 else "SHORT")
            print(
                f"[{item_id}] {title}\n"
                f"  voice={item_voice}  "
                f"target={format_seconds(target)}  "
                f"estimate≈{format_seconds(estimated)}  "
                f"delta={delta:+d}s  [{status}]"
            )
            if item.get("screen_cues"):
                print("  screen cues:")
                for cue in item["screen_cues"]:
                    print(f"    - {cue}")
        else:
            print(
                f"[{item_id}] {title}  voice={item_voice}  "
                f"estimate≈{format_seconds(estimated)}"
            )

        out = convert_text_to_speech(
            text,
            engine=engine,
            voice=item_voice,
            lang=lang,
            output_path=Path(f"{item_id}.mp3"),
        )
        print(f"  saved: {out}\n")
        generated += 1

    if only_id and generated == 0:
        print(f"No catalog entry matched id={only_id!r}")
        return 1
    if generated == 0:
        print("Nothing generated.")
        return 1
    return 0


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Convert text to speech (MP3).")
    parser.add_argument("text", nargs="?", help="Text to convert.")
    parser.add_argument("--file", "-f", help="Read text from a file.")
    parser.add_argument("--lang", "-l", default="en", help="gTTS language code.")
    parser.add_argument(
        "--voice",
        "-V",
        default=DEFAULT_VOICE,
        help=f"edge-tts voice (default: {DEFAULT_VOICE})",
    )
    parser.add_argument(
        "--engine",
        choices=("edge", "gtts"),
        default="edge",
        help="TTS engine (default: edge).",
    )
    parser.add_argument("--out", "-o", help="Output MP3 filename under output/.")
    parser.add_argument("--slow", action="store_true", help="gTTS slow mode.")
    parser.add_argument(
        "--catalog",
        "-c",
        nargs="?",
        const=str(SCRIPTS_DIR / "catalog.json"),
        help="Generate from scripts/catalog.json (or path).",
    )
    parser.add_argument("--only", help="With --catalog, generate only this id.")
    return parser.parse_args()


def main() -> int:
    args = parse_args()

    if args.catalog is not None:
        catalog_path = Path(args.catalog)
        if not catalog_path.is_absolute():
            catalog_path = ROOT / catalog_path
        if not catalog_path.exists():
            print(f"Catalog not found: {catalog_path}")
            return 1
        try:
            return generate_from_catalog(
                catalog_path,
                only_id=args.only,
                engine=args.engine,
                voice=args.voice,
            )
        except Exception as exc:  # noqa: BLE001
            print(f"Error: {exc}")
            return 1

    if args.file:
        path = Path(args.file)
        if not path.is_absolute():
            path = ROOT / path
        if not path.exists():
            print(f"File not found: {path}")
            return 1
        text = path.read_text(encoding="utf-8")
    elif args.text:
        text = args.text
    else:
        print("Text-to-Speech Converter")
        print(f"Default voice: {DEFAULT_VOICE}")
        print("Tip: python tts_converter.py --catalog\n")
        text = input("Text: ").strip()
        if not text:
            print("Cancelled.")
            return 0

    try:
        estimated = estimate_seconds(text)
        print(f"Estimate ≈ {format_seconds(estimated)} at ~{WORDS_PER_MINUTE} wpm")
        print(f"Engine={args.engine}  voice={args.voice}")
        out = convert_text_to_speech(
            text,
            engine=args.engine,
            voice=args.voice,
            lang=args.lang,
            slow=args.slow,
            output_path=Path(args.out) if args.out else None,
        )
    except Exception as exc:  # noqa: BLE001
        print(f"Error: {exc}")
        return 1

    print(f"Saved: {out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
