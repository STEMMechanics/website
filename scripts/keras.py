import io
import sys
import base64
import numpy as np
import keras_ocr

if len(sys.argv) > 1:
    # Decode the base64-encoded image
    img = base64.b64decode(sys.argv[1])
    img = np.array(bytearray(img), dtype=np.uint8)

    # Use Keras-OCR to recognize text in the image
    pipeline = keras_ocr.pipeline.Pipeline()
    prediction = pipeline.recognize([img])

    # Return the recognized text
    print prediction[0][0]['text']
