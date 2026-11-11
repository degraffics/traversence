# Traversence video worker (decisions/0153 §8, 0154 §2): ffmpeg makes feed-sized copies of uploaded videos.
FROM python:3.12-slim
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY video.py .
CMD ["python", "-u", "video.py"]
