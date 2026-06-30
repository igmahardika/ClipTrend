#!/usr/bin/env python3
import sys
import json
import os
import cv2
import numpy as np

try:
    import librosa
except ImportError:
    print(json.dumps({"error": "librosa not installed"}))
    sys.exit(1)

def extract_audio_signals(audio_path):
    try:
        y, sr = librosa.load(audio_path, sr=16000)
        
        # 1. Silence Detection
        # top_db=30 means anything 30dB below the peak is considered silence
        non_mute_intervals = librosa.effects.split(y, top_db=35)
        
        silences = []
        last_end = 0.0
        for start_i, end_i in non_mute_intervals:
            start_sec = start_i / sr
            if start_sec - last_end > 0.45: # 450ms minimum silence to jump-cut
                silences.append({"start": round(last_end, 2), "end": round(start_sec, 2)})
            last_end = end_i / sr
            
        total_duration = len(y) / sr
        if total_duration - last_end > 0.45:
            silences.append({"start": round(last_end, 2), "end": round(total_duration, 2)})

        # 2. RMS Energy Per Second
        rms = librosa.feature.rms(y=y, frame_length=16000, hop_length=16000)[0]
        max_rms = np.max(rms) if np.max(rms) > 0 else 1
        energy_per_sec = [round(float((val / max_rms) * 100), 1) for val in rms]

        return {"silences": silences, "audio_energy": energy_per_sec}
    except Exception as e:
        return {"error": str(e), "silences": [], "audio_energy": []}

def extract_motion_signals(video_path):
    try:
        cap = cv2.VideoCapture(video_path)
        if not cap.isOpened():
            return {"motion_energy": []}
            
        fps = cap.get(cv2.CAP_PROP_FPS)
        if fps <= 0: fps = 30
        
        frame_skip = int(fps / 2) # check 2 frames per second
        motion_scores = []
        
        ret, prev_frame = cap.read()
        if not ret: return {"motion_energy": []}
        
        prev_gray = cv2.cvtColor(prev_frame, cv2.COLOR_BGR2GRAY)
        prev_gray = cv2.resize(prev_gray, (320, 180))
        
        frame_count = 0
        second_motion = 0
        samples = 0
        
        while True:
            ret, frame = cap.read()
            if not ret: break
            
            frame_count += 1
            if frame_count % frame_skip == 0:
                gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
                gray = cv2.resize(gray, (320, 180))
                
                # Simple frame differencing
                diff = cv2.absdiff(prev_gray, gray)
                score = np.sum(diff) / (320 * 180 * 255) # normalized 0-1
                second_motion += float(score)
                samples += 1
                
                prev_gray = gray
                
            if frame_count % int(fps) == 0:
                avg = (second_motion / samples) * 100 if samples > 0 else 0
                motion_scores.append(round(min(100.0, avg * 10), 1)) # amplify score
                second_motion = 0
                samples = 0

        cap.release()
        return {"motion_energy": motion_scores}
    except Exception as e:
        return {"error": str(e), "motion_energy": []}

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Usage: extract_signals.py <video_path> <audio_path>"}))
        sys.exit(1)

    video_path = sys.argv[1]
    audio_path = sys.argv[2]
    
    result = {
        "audio": extract_audio_signals(audio_path),
        "video": extract_motion_signals(video_path)
    }
    
    print(json.dumps(result))
