<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use thiagoalessio\TesseractOCR\TesseractOCR;
use FFMpeg;

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
            $data = ['ocr' => []];

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
            $data['ocr']['raw'] = $result;

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

            $data['ocr']['greyscale'] = $result;
            imagedestroy($imgcreate);

            // Double Scale
            $result = '';
            $srcImage = imagecreatefrompng($tmpfname);
            $srcWidth = imagesx($srcImage);
            $srcHeight = imagesy($srcImage);

            $dstWidth = ($srcWidth * 2);
            $dstHeight = ($srcHeight * 2);
            $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);

            // Copy and resize the original image onto the new canvas
            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

            // Generate a temporary filename for the doubled-scale image
            $tmpfname_scaled = tempnam(sys_get_temp_dir(), 'double_scale');
            imagepng($dstImage, $tmpfname_scaled);
            imagedestroy($srcImage);
            imagedestroy($dstImage);

            // OCR it
            $ocr->image($tmpfname_scaled);
            $result = $ocr->run(500);
            unlink($tmpfname_scaled);
            $data['ocr']['double_scale'] = $result;

            // Half Scale
            $result = '';
            $srcImage = imagecreatefrompng($tmpfname);
            $srcWidth = imagesx($srcImage);
            $srcHeight = imagesy($srcImage);

            $dstWidth = ($srcWidth / 2);
            $dstHeight = ($srcHeight / 2);
            $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);

            // Copy and resize the original image onto the new canvas
            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

            // Generate a temporary filename for the doubled-scale image
            $tmpfname_scaled = tempnam(sys_get_temp_dir(), 'double_scale');
            imagepng($dstImage, $tmpfname_scaled);
            imagedestroy($srcImage);
            imagedestroy($dstImage);

            // OCR it
            $ocr->image($tmpfname_scaled);
            $result = $ocr->run(500);
            unlink($tmpfname_scaled);
            $data['ocr']['half_scale'] = $result;

            // EdgeDetect
            $result = '';
            $imgcreate = imagecreatefrompng($tmpfname);
            if ($imgcreate !== false && imagefilter($imgcreate, IMG_FILTER_EDGEDETECT) === true) {
                $tmpfname_edgedetect = $basefile_path . '_edgedetect.png';
                imagepng($imgcreate, $tmpfname_edgedetect);
                $ocr->image($tmpfname_edgedetect);
                $result = $ocr->run(500);
            }

            $data['ocr']['edge_detect'] = $result;
            imagedestroy($imgcreate);

            // Mean Removal
            $result = '';
            $imgcreate = imagecreatefrompng($tmpfname);
            if ($imgcreate !== false && imagefilter($imgcreate, IMG_FILTER_MEAN_REMOVAL) === true) {
                $tmpfname_edgedetect = $basefile_path . '_meanremoval.png';
                imagepng($imgcreate, $tmpfname_edgedetect);
                $ocr->image($tmpfname_edgedetect);
                $result = $ocr->run(500);
            }
            $data['ocr']['mean_removal'] = $result;
            imagedestroy($imgcreate);

            // Negate
            $result = '';
            $imgcreate = imagecreatefrompng($tmpfname);
            if ($imgcreate !== false && imagefilter($imgcreate, IMG_FILTER_NEGATE) === true) {
                $tmpfname_edgedetect = $basefile_path . '_negate.png';
                imagepng($imgcreate, $tmpfname_edgedetect);
                $ocr->image($tmpfname_edgedetect);
                $result = $ocr->run(500);
            }
            $data['ocr']['negate'] = $result;
            imagedestroy($imgcreate);

            // Pixelate
            $result = '';
            $imgcreate = imagecreatefrompng($tmpfname);
            if ($imgcreate !== false && imagefilter($imgcreate, IMG_FILTER_PIXELATE, 3) === true) {
                $tmpfname_edgedetect = $basefile_path . '_pixelate.png';
                imagepng($imgcreate, $tmpfname_edgedetect);
                $ocr->image($tmpfname_edgedetect);
                $result = $ocr->run(500);
            }
            $data['ocr']['pixelate'] = $result;
            imagedestroy($imgcreate);

            // keras
            $cmd = 'python3 ' . base_path() . '/scripts/keras_oc.py ' . $url;
            $command = escapeshellcmd($cmd); #no special characters it will work
            $data['ocr']['keras'] = shell_exec($command);

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
