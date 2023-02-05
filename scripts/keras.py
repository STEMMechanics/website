import sys
import urllib.parse
import numpy as np
import keras_ocr

if len(sys.argv) > 1:
    url = urllib.parse.unquote(sys.argv[1])
    image = keras_ocr.tools.read(url)
    pipeline = keras_ocr.pipeline.Pipeline()
    prediction = pipeline.recognize([image])
    print("----------START----------")
    for text, box in prediction [0]:
        print(text)
