#!/usr/bin/env python3

import sys
import os
import cv2
import numpy as np

try:
    import mediapipe as mp
except ImportError:
    print("Error: mediapipe is not installed. Run 'pip3 install mediapipe opencv-python numpy'", file=sys.stderr)
    sys.exit(1)

def smooth_crop(input_path, output_path):
    if not os.path.exists(input_path):
        print(f"Error: Input file {input_path} not found.", file=sys.stderr)
        sys.exit(1)
        
    cap = cv2.VideoCapture(input_path)
    if not cap.isOpened():
        print(f"Error: Cannot open video {input_path}.", file=sys.stderr)
        sys.exit(1)
        
    width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
    height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
    fps = cap.get(cv2.CAP_PROP_FPS)
    if fps == 0 or np.isnan(fps):
        fps = 30.0
        
    # We want a 9:16 aspect ratio vertical crop
    target_width = 1080
    target_height = 1920
    
    # Scale video so height matches 1920, and we crop the width.
    # We maintain original aspect ratio for scaling.
    scale_factor = target_height / height
    scaled_width = int(width * scale_factor)
    
    crop_w = target_width
    crop_h = target_height
    
    # FourCC and VideoWriter
    fourcc = cv2.VideoWriter_fourcc(*'avc1') # Use h264 natively
    out = cv2.VideoWriter(output_path, fourcc, fps, (target_width, target_height))
    
    mp_face_detection = mp.solutions.face_detection
    
    # Exponential moving average for smooth camera panning
    alpha = 0.15 
    current_x_center = scaled_width / 2 # start at center
    
    with mp_face_detection.FaceDetection(model_selection=1, min_detection_confidence=0.5) as face_detection:
        while True:
            ret, frame = cap.read()
            if not ret:
                break
                
            # Resize frame to target height (1920)
            resized = cv2.resize(frame, (scaled_width, target_height))
            
            # Mediapipe requires RGB
            results = face_detection.process(cv2.cvtColor(resized, cv2.COLOR_BGR2RGB))
            
            detected_x_center = current_x_center
            
            if results.detections:
                # Find the most prominent face (largest bounding box)
                largest_face = max(results.detections, key=lambda d: d.location_data.relative_bounding_box.width * d.location_data.relative_bounding_box.height)
                bbox = largest_face.location_data.relative_bounding_box
                
                # Bounding box coordinates are relative [0.0, 1.0]
                face_x = int(bbox.xmin * scaled_width)
                face_w = int(bbox.width * scaled_width)
                
                detected_x_center = face_x + (face_w / 2)
            
            # Apply EMA smoothing to the center
            current_x_center = (alpha * detected_x_center) + ((1 - alpha) * current_x_center)
            
            # Calculate crop start X
            start_x = int(current_x_center - (crop_w / 2))
            
            # Clamp bounds
            if start_x < 0:
                start_x = 0
            if start_x + crop_w > scaled_width:
                start_x = scaled_width - crop_w
                
            # Perform the crop
            cropped_frame = resized[0:crop_h, start_x:start_x+crop_w]
            
            out.write(cropped_frame)

    cap.release()
    out.release()
    
    print(f"Smart crop completed: {output_path}")

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print("Usage: python smart_crop.py <input_video> <output_video>", file=sys.stderr)
        sys.exit(1)
        
    smooth_crop(sys.argv[1], sys.argv[2])
