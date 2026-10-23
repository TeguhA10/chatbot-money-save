import os
import tempfile
import logging
from fastapi import FastAPI, UploadFile, File, HTTPException
from faster_whisper import WhisperModel

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("voice-service")

app = FastAPI(title="Local Offline Whisper STT Service")

MODEL_SIZE = os.getenv("WHISPER_MODEL", "tiny")
LANGUAGE = os.getenv("WHISPER_LANGUAGE", "id")

logger.info(f"Loading faster-whisper model '{MODEL_SIZE}' (CPU, int8 quantization)...")
model = WhisperModel(MODEL_SIZE, device="cpu", compute_type="int8")
logger.info("Model loaded successfully and ready to transcribe!")

@app.get("/health")
def health():
    return {
        "status": "ok",
        "service": "voice-service",
        "model": MODEL_SIZE,
        "language": LANGUAGE,
    }

@app.post("/transcribe")
async def transcribe(file: UploadFile = File(...)):
    if not file:
        raise HTTPException(status_code=400, detail="No audio file provided")

    with tempfile.NamedTemporaryFile(delete=False, suffix=".ogg") as tmp:
        content = await file.read()
        tmp.write(content)
        tmp_path = tmp.name

    try:
        # Bias model towards Indonesian financial transaction vocabulary
        prompt = "Pencatatan keuangan: keluar 25000 makan siang, beli bensin 15rb, transfer 500rb, gaji 5jt, bayar pulsa 50rb."

        segments, info = model.transcribe(
            tmp_path,
            language=LANGUAGE,
            beam_size=1,
            initial_prompt=prompt,
            vad_filter=True, # Voice Activity Detection to filter out silence/noise
        )

        text = " ".join([segment.text for segment in segments]).strip()
        logger.info(f"Transcribed ({info.language}, prob={info.language_probability:.2f}): '{text}'")

        return {
            "text": text,
            "language": info.language,
            "duration": info.duration,
        }
    except Exception as e:
        logger.error(f"Error during transcription: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if os.path.exists(tmp_path):
            try:
                os.remove(tmp_path)
            except Exception:
                pass
