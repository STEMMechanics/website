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

            $oem = $request->get('oem');
            $digits = $request->get('digits');
            $allowlist = $request->get('allowlist');

            $tmpfname = tempnam(sys_get_temp_dir(), 'download');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $curlResult = curl_exec($ch);
            curl_close($ch);

            file_put_contents($tmpfname, $curlResult);

            // Raw OCR
            $ocr = new TesseractOCR();
            $ocr->image($tmpfname);
            if ($oem !== null) {
                $ocr->oem($oem);
            }
            if ($digits !== null) {
                $ocr->digits();
            }
            if ($allowlist !== null) {
                $ocr->allowlist($allowlist);
            }
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


    // function is_ani($filename) {
    //     if(!($fh = @fopen($filename, 'rb')))
    //       return false;
    //     $count = 0;
    //     //an animated gif contains multiple "frames", with each frame having a
    //     //header made up of:
    //     // * a static 4-byte sequence (\x00\x21\xF9\x04)
    //     // * 4 variable bytes
    //     // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)

    //     // We read through the file til we reach the end of the file, or we've found
    //     // at least 2 frame headers
    //     $chunk = false;
    //     while(!feof($fh) && $count < 2) {
    //       //add the last 20 characters from the previous string, to make sure the searched pattern is not split.
    //       $chunk = ($chunk ? substr($chunk, -20) : "") . fread($fh, 1024 * 100); //read 100kb at a time
    //       $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
    //     }

    //     fclose($fh);
    //     return $count > 1;
    //   }
}
