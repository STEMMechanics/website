<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use thiagoalessio\TesseractOCR\TesseractOCR;

class OCRController extends ApiController
{
    /**
     * ApplicationController constructor.
     */
    public function __construct()
    {
        // $this->middleware('auth:sanctum')
        // ->only(['show']);
    }

    /**
     * Display the specified resource.
     *
     * @param  Request $request The log request.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        // if ($request->user()?->hasPermission('logs/' . $name) === true) {
        $url = $request->get('url');
        if ($url !== null) {
            $data = [];

            $tmpfname = tempnam(sys_get_temp_dir(), 'download');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $data = curl_exec($ch);
            curl_close($ch);

            file_put_contents($tmpfname, $data);

            // Raw OCR
            $ocr = new TesseractOCR();
            $ocr->image($tmpfname);
            $result = $ocr->run(500);
            $data['ocr_raw'] = $result;

            $basefile_path = preg_replace('/\\.[^.\\s]{3,4}$/', '', $tmpfname);

            // Greyscale OCR
            $result = '';
            $imgcreate = imagecreatefrompng($tmpfname);
            if ($imgcreate !== false && imagefilter($imgcreate, IMG_FILTER_GRAYSCALE) === true) {
                $tmpfname_greyscape = $basefile_path . '_grayscale.png';
                imagepng($imgcreate, $tmpfname_greyscape);
                $ocr->image($tmpfname_greyscape);
                $result = $ocr->run(500);
            }

            $data['ocr_greyscale'] = $result;
            imagedestroy($imgcreate);

            unlink($tmpfname);
            return $this->respondJson($data);
        }//end if

        return $this->respondWithErrors(['url' => 'url is missing']);
    }


    // $ffmpeg = FFMpeg\FFMpeg::create();

    // // Load the input video
    // $inputFile = $ffmpeg->open('input.mp4');

    // // Split the video into individual frames
    // $videoFrames = $inputFile->frames();
    // foreach ($videoFrames as $frame) {
    //   // Save the frame as a PNG
    //   $frame->save(new FFMpeg\Format\Video\PNG(), 'frame-' . $frame->getMetadata('pts') . '.png');

    //   // Pass the PNG to Tesseract for processing
    //   exec("tesseract frame-" . $frame->getMetadata('pts') . ".png output");
    // }

    // // Read the output from Tesseract
    // $text = file_get_contents("output.txt");

    // // Do something with the text from Tesseract
    // echo $text;
}
