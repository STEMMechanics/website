<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use thiagoalessio\TesseractOCR\TesseractOCR;
use FFMpeg;
use App\Enum\CurlErrorCodes;

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

            $filters = $request->get('filters', ['tesseract']);
            if (is_array($filters) === false) {
                $filters = explode(',', $filters);
            }

            $tesseractOEM = $request->get('tesseract.oem');
            $tesseractDigits = $request->get('tesseract.digits');
            $tesseractAllowlist = $request->get('tesseract.allowlist');

            // Download URL
            $urlDownloadFilePath = tempnam(sys_get_temp_dir(), 'download');
            $maxDownloadSize = (1024 * 1024); // 1MB
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            // We need progress updates to break the connection mid-way
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 128); // more progress info
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function (
                $downloadSize,
                $downloaded,
                $uploadSize,
                $uploaded
            ) use ($maxDownloadSize) {
                return ($downloaded > $maxDownloadSize) ? 1 : 0;
            });

            $curlResult = curl_exec($ch);
            $curlError = curl_errno($ch);
            $curlSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            curl_close($ch);
            if ($curlError !== 0) {
                $error = 'File size is larger then allowed';
                if ($curlError !== CurlErrorCodes::CURLE_ABORTED_BY_CALLBACK) {
                    $error = CurlErrorCodes::getMessage($curlError);
                }

                return $this->respondWithErrors(['url' => $error]);
            }

            // Save url file
            file_put_contents($urlDownloadFilePath, $curlResult);
            $urlDownloadFilePathBase = preg_replace('/\\.[^.\\s]{3,4}$/', '', $urlDownloadFilePath);

            // tesseract (overall)
            $ocr = null;
            foreach ($filters as $filterItem) {
                if (str_starts_with($filterItem, 'tesseract') === true) {
                    $ocr = new TesseractOCR();
                    $ocr->image($urlDownloadFilePath);
                    if ($tesseractOEM !== null) {
                        $ocr->oem($tesseractOEM);
                    }
                    if ($tesseractDigits !== null) {
                        $ocr->digits();
                    }
                    if ($tesseractAllowlist !== null) {
                        $ocr->allowlist($tesseractAllowlist);
                    }
                    break;
                }
            }

            // Image Filter Function
            $tesseractImageFilterFunc = function ($filter, $options = null) use ($curlResult, $curlSize, $ocr) {
                $result = '';
                $img = imagecreatefromstring($curlResult);
                if ($img !== false && (($options !== null && imagefilter($img, $filter, $options) === true) || ($options === null && imagefilter($img, $filter) === true))) {
                    ob_start();
                    imagepng($img);
                    $imgData = ob_get_contents();
                    ob_end_clean();
                    $imgDataSize = strlen($imgData);

                    $ocr->imageData($imgData, $imgDataSize);
                    imagedestroy($img);

                    $result = $ocr->run(500);
                }

                return $result;
            };

            // Image Scale Function
            $tesseractImageScaleFunc = function ($scaleFunc) use ($curlResult, $ocr) {
                $result = '';
                $srcImage = imagecreatefromstring($curlResult);
                $srcWidth = imagesx($srcImage);
                $srcHeight = imagesy($srcImage);

                $dstWidth = $scaleFunc($srcWidth);
                $dstHeight = $scaleFunc($srcHeight);
                $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);

                imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

                ob_start();
                imagepng($dstImage);
                $imgData = ob_get_contents();
                ob_end_clean();
                $imgDataSize = strlen($imgData);

                imagedestroy($srcImage);
                imagedestroy($dstImage);

                $ocr->imageData($imgData, $imgDataSize);
                $result = $ocr->run(500);
                return $result;
            };

            // filter: tesseract
            if (in_array('tesseract', $filters) === true) {
                $data['ocr']['tesseract'] = $ocr->run(500);
            }

            // filter: tesseract.grayscale
            if (in_array('tesseract.grayscale', $filters) === true) {
                $data['ocr']['tesseract.grayscale'] = $tesseractImageFilterFunc(IMG_FILTER_GRAYSCALE);
            }

            // filter: tesseract.double_scale
            if (in_array('tesseract.double_scale', $filters) === true) {
                $data['ocr']['tesseract.double_scale'] = $tesseractImageScaleFunc(function ($size) {
                    return $size * 2;
                });
            }

            // filter: tesseract.half_scale
            if (in_array('tesseract.half_scale', $filters) === true) {
                $data['ocr']['tesseract.half_scale'] = $tesseractImageScaleFunc(function ($size) {
                    return $size / 2;
                });
            }

            // filter: tesseract.edgedetect
            if (in_array('tesseract.edgedetect', $filters) === true) {
                $data['ocr']['tesseract.edgedetect'] = $tesseractImageFilterFunc(IMG_FILTER_EDGEDETECT);
            }

            // filter: tesseract.mean_removal
            if (in_array('tesseract.mean_removal', $filters) === true) {
                $data['ocr']['tesseract.mean_removal'] = $tesseractImageFilterFunc(IMG_FILTER_MEAN_REMOVAL);
            }

            // filter: tesseract.negate
            if (in_array('tesseract.negate', $filters) === true) {
                $data['ocr']['tesseract.negate'] = $tesseractImageFilterFunc(IMG_FILTER_NEGATE);
            }

            // filter: tesseract.pixelate
            if (in_array('tesseract.pixelate', $filters) === true) {
                $data['ocr']['tesseract.pixelate'] = $tesseractImageFilterFunc(IMG_FILTER_PIXELATE, 3);
            }

            // filter: keras
            if (in_array('keras', $filters) === true) {
                $cmd = '/usr/bin/python3 ' . base_path() . '/scripts/keras_oc.py ' . urlencode($url);
                $command = escapeshellcmd($cmd);
                $output = shell_exec($cmd);
                if ($output !== null && strlen($output) > 0) {
                    $output = substr($output, (strpos($output, '----------START----------') + 25));
                } else {
                    $output = '';
                }
                $data['ocr']['keras'] = $output;
            }

            unlink($urlDownloadFilePath);
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
