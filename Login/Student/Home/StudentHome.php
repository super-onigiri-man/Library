<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit;
}
$user_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : "";
$user_id = json_encode($user_id);
$user_name = isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : "";
$user_type_name = isset($_SESSION["user_type_name"]) ? $_SESSION["user_type_name"] : "";
$affiliation_name = isset($_SESSION["affiliation_name"]) ? $_SESSION["affiliation_name"] : "";
$affiliation_id = isset($_SESSION["affiliation_id"]) ? $_SESSION["affiliation_id"] : "";
// データベース接続情報
$host = "localhost";
$user = "root";
$pwd = "moku2440";
$dbname = "library";
// dsnは以下の形でどのデータベースかを指定する
$dsn = "mysql:host={$host};port=3306;dbname={$dbname};";
try {
    //データベースに接続
    $conn = new PDO($dsn, $user, $pwd);
    // ログインしているユーザーが借りている本の情報を取得するためのSQL文
    $sql = "SELECT l.user_id, b.book_id, b.book_name, b.author, b.publisher, b.remarks, b.image, l.lending_status
                FROM book AS b
                INNER JOIN lent AS l
                ON l.book_id = b.book_id
                WHERE b.affiliation_id = :affiliation_id AND l.user_id = :user_id AND l.lending_status = 'impossible'";
    //SQL実行準備
    $stmt = $conn->prepare($sql);
    //:user_idにセッション変数から取得したuser_idを代入
    $stmt->bindValue(":user_id", $user_id);
    $stmt->bindValue(":affiliation_id", $affiliation_id);
    //SQL文を実行
    $res = $stmt->execute();
    //借りているの情報を変数resultに配列として代入
    $lentNow = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //変数lentNowをJSON化してlentNowに代入
    $lentNow = json_encode($lentNow);
    // ログインしているユーザーのクラスで貸し出している本を取り出すSQL文
    $sql = "SELECT l.user_id, b.book_id, b.book_name, b.author, b.publisher, b.remarks, b.image, l.lending_status
                FROM book AS b
                LEFT JOIN lent AS l
                ON l.book_id = b.book_id
                WHERE b.affiliation_id = :affiliation_id";
    //SQL実行準備
    $stmt = $conn->prepare($sql);
    //:user_idにセッション変数から取得したuser_idを代入
    $stmt->bindValue(":affiliation_id", $affiliation_id);
    //SQL文を実行
    $stmt->execute();
    //所属しているクラス全ての本の情報を変数allBooksに配列として代入
    $allBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //変数allBooksをJSON化してallBooksに代入
    $allBooks = json_encode($allBooks);
    // ログインしているユーザーが所属しているクラスの全ての貸出中の本の名前とパスを取得するためのSQL文
    $sql = "select book_id from book
       where book_id in(select book_id from lent where lending_status = 'impossible')";
    //SQL文を実行
    $stmt = $conn->query($sql);
    //所属しているクラス全ての本の情報を変数allBooksに配列として代入
    $lentBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //変数allBooksをJSON化してallBooksに代入
    $lentBooks = json_encode($lentBooks);
    echo gettype($lentBooks);
} catch (PDOException $e) {
    //データベースへの接続失敗
    $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="Home.css">
</head>
<body>
    <!--<h1 id="grayAdd">aaaaa</h1>!-->
    <!--ヘッダー-->
    <!--ログアウト-->
    <button id="login-button" class="login-button" onclick="window.location.href='../../Logout.php'">ログアウト</button><br>
    <div class="main">
        <div id="contents"><a href="Home.php">白石学園ポータルサイト</a>
        </div>
        <div id="login">
            ログイン者:<?php echo $_SESSION["user_name"] ?><br>
            区分:<?php echo $_SESSION["user_type_name"] ?><br>
            学科:<?php echo $_SESSION["affiliation_name"] ?>
        </div>
    </div>
    <!--履歴ボタン-->
    <button id="history-button" class="history-button" onclick="window.location.href='../History/History.php'">履歴</button>
    <button id="Qr-button" class="Qr-button">本のQRコード読み取り</button><br>
    <!--パンくず-->
    <ul class="breadcrumb">
        <li><a href="../Home/StudentHome.php">ホーム</a></li>
    </ul>
    <!--検索欄と検索ボタン-->
    <div class="search">
        <input type="text" id="search-input" class="search-input" placeholder="検索欄">
        <button id="search-button" class="search-button" onclick="searchImages()">検索</button>
    </div><br>
    <p>あなたが貸出中の本</p>
    <br>
    <div class="my-image-container"></div>
    <hr style="border-top: 1px solid #007BFF; ;height:1px;width:100%;"><!--横線-->
    <p>全ての本</p>
    <div class="all-image-container"></div>
    <script>
        //文字を改行する関数
        function insertLineBreaks(text, maxLength) {
            let result = '';
            let count = 0;
            for (let i = 0; i < text.length; i++) {
                result += text[i];
                count++;
                if (count === maxLength && i !== text.length - 1) {
                    result += '\n';
                    count = 0;
                }
            }
            return result;
        }
        //画像を表示する関数
        function displayImages(totalImages, imageDirectoryPath, flag) {
            //flagがtrueの時はimage-containerのクラスを参照,そうでないときはimage-container1を参照しsetClassに代入
            let setClass = "";
            if (flag) {
                setClass = ".my-image-container";
            } else {
                setClass = ".all-image-container";
            }
            let container = document.querySelector(setClass);
            //totalImages配列の要素数よりimageIndexが小さいとき,画像とテキストを表示する
            for (let imageIndex = 0; imageIndex < totalImages.length; imageIndex++) {
                //imageWrapper変数に新規のdivタグを代入
                const imageWrapper = document.createElement("div");
                //imageWrapperにimage-wrapperクラスを追加
                imageWrapper.classList.add("image-wrapper");
                //imgタグを生成
                const imageElement = new Image();
                //book_idが貸出中の本のbook_idと等しいとき画像をグレーにする
                for (let i = 0; i < lentBooks.length; i++) {
                    // 貸出中のbook_idであれば、画像を灰色にする
                    if ((lentBooks[i].book_id == totalImages[imageIndex].book_id)) {
                        imageElement.classList.add("grayAdd");
                    }
                }
                //pathに画像パスを代入
                let path = imageDirectoryPath + totalImages[imageIndex].image;
                //imgタグのsrcにpathを代入
                imageElement.src = path;
                imageElement.addEventListener("click", () => {
                    // 遷移先リンク
                    let link = judge(imageElement, totalImages, imageIndex);
                    window.location.href = link;
                    let f = document.createElement("form");
                    f.method = 'POST';
                    f.action = link;
                    f.innerHTML = '<input name="book_name" value=' + totalImages[imageIndex].book_name + '>';
                    document.body.append(f);
                    f.submit();
                });
                //imageWrapperの子要素にimageElementを追加
                imageWrapper.appendChild(imageElement);
                //imageText変数に新規のdivタグを代入
                const imageText = document.createElement("div");
                //imageText変数にimage-textクラスを追加
                imageText.classList.add("image-text");
                //textContentにテキストを追加
                const textContent = totalImages[imageIndex].book_name;
                const formattedText = insertLineBreaks(textContent, 10);
                imageText.textContent = formattedText;
                imageWrapper.appendChild(imageText);
                container.appendChild(imageWrapper);
            }
        }
        // 検索する関数
        function searchImages() {
            const searchText = document.getElementById("search-input").value.toLowerCase();
            const imageWrappers = document.querySelectorAll(".image-wrapper");
            // 書籍名と検索テキストが一致したら検索できる
            for (let i = 0; i < imageWrappers.length; i++) {
                const imageText = imageWrappers[i].querySelector(".image-text").textContent.toLowerCase();
                // 改行文字を削除して一致判定を行う
                const formattedSearchText = searchText.replace(/\n/g, '');
                const formattedImageText = imageText.replace(/\n/g, '');
                if (formattedImageText.includes(formattedSearchText)) {
                    imageWrappers[i].style.display = "block";
                } else {
                    imageWrappers[i].style.display = "none";
                }
            }
        }
        // 履歴ボタンがクリックされたときの処理関数
        function Returnlink() {
            window.location.href = "history.html";
        }
        //表示
        document.getElementById("search-button").addEventListener("click", searchImages);
        //PHPからJSONとして受け取ったlentNow(借りている本)をlentNowに配列として代入
        var lentNow = <?php echo $lentNow; ?>;
        //PHPからJSONとして受け取ったallBooks(所属しているクラスの全ての本)をallBooksに配列として代入
        var allBooks = <?php echo $allBooks; ?>;
        //PHPからJSONとして受け取ったallBooks(所属しているクラスの全ての本)をlentBooksに配列として代入
        var lentBooks = <?php echo $lentBooks; ?>;
        var user_id = <?php echo $user_id; ?>;
        alert(typeof allBooks);
        displayImages(lentNow, "../../../Image/", true);
        displayImages(allBooks, "../../../Image/", false);
        ////貸出、返却、その他の判別
function judge(totalImages, imageIndex) {
    for (let i = 0; i < lentBooks.length; i++) {
        if (lentBooks[i].book_id === totalImages[imageIndex].book_id) {
            if (totalImages[imageIndex].user_id === user_id) {
                return "../Lent/Lent.php";
            } else {
                return "../../Login.php";
            }
        } else {
            return "Test.php";
        }
    }
}
function openQrCodeWindow() {
  var qrCodeWindow = window.open('Qrcode.html', '_blank', 'width=600,height=400');
  qrCodeWindow.onbeforeunload = function() {
    var qrCodeResult = qrCodeWindow.document.getElementById('qr').value;
    var qrCodeValue = parseInt(qrCodeResult);
    var loginUrl = judge(allBooks, qrCodeValue);
    window.location.href = loginUrl;
  };
}
  document.getElementById('Qr-button').addEventListener('click', () => {
    openQrCodeWindow();
  });
</script>
</body>
</html>