<?php
session_start();

require_once "config/db.php";

/* ======================================================
   Validate Product ID
====================================================== */

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {

    header("Location: menu.php");
    exit();

}

$product_id = (int)$_GET['id'];


/* ======================================================
   Fetch Product
====================================================== */

$stmt = $pdo->prepare("

SELECT

p.*,
c.category_name

FROM products p

LEFT JOIN categories c

ON c.category_id = p.category_id

WHERE

p.product_id = ?

AND p.status='Active'

LIMIT 1

");

$stmt->execute([$product_id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$product){

    header("Location: menu.php");
    exit();

}


/* ======================================================
   Product Images
====================================================== */

$imageStmt = $pdo->prepare("

SELECT

*

FROM product_images

WHERE product_id=?

ORDER BY

is_primary DESC,
display_order ASC,
image_id ASC

");

$imageStmt->execute([$product_id]);

$images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);


/* ======================================================
   Main Image
====================================================== */

$mainImage = "assets/images/no-image.png";

if(!empty($images)){

    $mainImage =
        "assets/images/products/" .
        $images[0]['image_name'];

}


/* ======================================================
   Discount
====================================================== */

$price = (float)$product['price'];

$discount = (float)$product['discount_percent'];

$finalPrice = $price;

if($discount>0){

    $finalPrice =
        $price -
        (($price*$discount)/100);

}


/* ======================================================
   Reviews
======================================================

Table Required

reviews

--------------------------------

review_id
product_id
user_id
rating
review_title
review
status
created_at

====================================================== */

$reviews = [];

$totalReviews = 0;

$averageRating = 0;

$ratingData = [
    5=>0,
    4=>0,
    3=>0,
    2=>0,
    1=>0
];

try{

    $reviewStmt = $pdo->prepare("

    SELECT

    r.*,
    u.name

    FROM reviews r

    LEFT JOIN users u

    ON u.user_id=r.user_id

    WHERE

    r.product_id=?
    AND r.status='Approved'

    ORDER BY
    r.created_at DESC

    ");

    $reviewStmt->execute([$product_id]);

    $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalReviews = count($reviews);

    if($totalReviews>0){

        $sum = 0;

        foreach($reviews as $row){

            $sum += $row['rating'];

            if(isset($ratingData[$row['rating']])){

                $ratingData[$row['rating']]++;

            }

        }

        $averageRating =
            round($sum/$totalReviews,1);

    }

}catch(Exception $e){

    $reviews=[];

}


/* ======================================================
   Rating Percentage
====================================================== */

$ratingPercent=[];

foreach($ratingData as $star=>$count){

    $ratingPercent[$star]=

    $totalReviews>0

    ?

    round(($count/$totalReviews)*100)

    :

    0;

}


/* ======================================================
   Related Products
====================================================== */

$relatedStmt=$pdo->prepare("

SELECT

p.*,

c.category_name,

pi.image_name

FROM products p

LEFT JOIN categories c

ON c.category_id=p.category_id

LEFT JOIN product_images pi

ON

pi.product_id=p.product_id

AND pi.is_primary=1

WHERE

p.category_id=?

AND p.product_id!=?

AND p.status='Active'

ORDER BY RAND()

LIMIT 4

");

$relatedStmt->execute([

$product['category_id'],

$product_id

]);

$relatedProducts=$relatedStmt->fetchAll(PDO::FETCH_ASSOC);


/* ======================================================
   Product Ingredients

Optional Table

product_ingredients

====================================================== */

$ingredients=[];

try{

    $ingredientStmt=$pdo->prepare("

    SELECT *

    FROM product_ingredients

    WHERE product_id=?

    ORDER BY ingredient_name

    ");

    $ingredientStmt->execute([$product_id]);

    $ingredients=

    $ingredientStmt->fetchAll(PDO::FETCH_ASSOC);

}catch(Exception $e){

    $ingredients=[];

}


/* ======================================================
   Page Title
====================================================== */

$pageTitle=

$product['product_name'];


/* ======================================================
   Include Layout
====================================================== */

require_once "includes/header.php";

require_once "includes/navbar.php";

?>
