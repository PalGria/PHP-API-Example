<?php
//Entry Point
//Connection definition
function getConnection()
{
    //Retrieve the DB variables from config.PHP
    $user = $_SESSION['user']; // session get
    $db = $_SESSION['db']; // session get
    $password = $_SESSION['password']; // session get
    $host = $_SESSION['host']; // session get
    $connection = new mysqli($host, $user, $password, $db);
    if ($connection->connect_errno) {
        echo "Error: Connection failed: \n";
        echo "Errno: " . $connection->connect_errno . "\n";
        echo "Error: " . $connection->connect_error . "\n";
        exit;
    }
    return $connection;
}
function getSQL($nodeId, $language, $search_keyword, $pageNum, $pageSize)
{
    $sql = getSQLByNodeLanguage($nodeId, $language, $pageNum, $pageSize);
    if (isset($search_keyword) && $search_keyword != "") {
        $sql = getSQLByNodeLanguageQuery($nodeId, $language, $search_keyword, $pageNum, $pageSize);
    }
    return $sql;
}

function getSQLByNodeLanguage($nodeId, $language, $pageNum, $pageSize)
{
    return "select x.idNode, y.nodeName, x.children
    FROM(
    SELECT DISTINCT Child.idNode, (  Child.iRight - Child.iLeft -1) as children
    FROM node_tree as Child, node_tree as Parent 
    WHERE Parent.iLeft < Child.iLeft AND Parent.iRight > Child.iRight  -- associate Child Nodes with ancestors
    GROUP BY Child.idNode, Child.iLeft, Child.iRight
    HAVING max(Parent.idNode) = $nodeId  -- Subset for those with the given Parent Node as the nearest ancestor
    ) as x 
    inner join 
    (
        select * from node_tree_names where language = '$language'
    ) as y on x.idNode = y.idNode
    LIMIT  $pageSize
    OFFSET $pageNum";
}
function getSQLByNodeLanguageQuery($nodeId, $language, $search_keyword, $pageNum, $pageSize)
{
    return "select x.idNode, y.nodeName
    FROM(
    SELECT DISTINCT Child.idNode
    FROM node_tree as Child, node_tree as Parent 
    WHERE Parent.iLeft < Child.iLeft AND Parent.iRight > Child.iRight  -- associate Child Nodes with ancestors
    GROUP BY Child.idNode, Child.iLeft, Child.iRight
    HAVING max(Parent.idNode) = $nodeId  -- Subset for those with the given Parent Node as the nearest ancestor
    ) as x 
    inner join 
    (
        select * from node_tree_names where language = '$language' and nodeName LIKE '$search_keyword'
    ) as y on x.idNode = y.idNode
    LIMIT  $pageSize
    OFFSET $pageNum";
}
function execQuery($sql, $connection)
{
    $data = $connection->query($sql);
    return $data;
}
function extractNodes($rawData)
{
    $result = [];
    while ($row = $rawData->fetch_assoc()) {
        $child["idNode"] = $row['idNode'];
        $child["nodeName"] = $row['nodeName'];
        $child["children"] = $row['children'];
        array_push($result, $child);
    }
    return $result;
}
function craftResponse($nodes, $error)
{
    $response = [];
    $response['nodes'] = $nodes;
    $response['error'] = $error;
    return $response;
}
function checkValues($nodeId, $language, $pageNum, $pageSize)
{
    $errors = [];
    if (!(isset($_GET['node_id']) && is_numeric($_GET['node_id']))) {
        array_push($errors, "Invalid node id");
    }
    if (!(isset($_GET['language']) && $_GET['language'] != "")) {
        array_push($errors, "Missing mandatory params");
    }
    if (isset($_GET['page_size']) && ($_GET['page_size'] < 0 || $_GET['page_size'] > 1000)) {
        array_push($errors, "Invalid page size requested");
    }
    if (isset($_GET['page_num']) && $_GET['page_num'] < 0) {
        array_push($errors, "Invalid page number requested");
    }
    return $errors;
}
//input definitions
$node_id = isset($_GET['node_id']) ? $_GET['node_id'] : null;
$language = isset($_GET['language']) ? $_GET['language'] : null;
$search_keyword = isset($_GET['search_keyword']) ? $_GET['search_keyword'] : "";
$page_num = isset($_GET['page_num']) && is_numeric($_GET['page_num']) ? $_GET['page_num'] : "0";
$page_size = isset($_GET['page_size']) && is_numeric($_GET['page_size']) ? $_GET['page_size'] : "100";
//Initial checks
$errors = checkValues($node_id, $language, $page_num, $page_size);
if (count($errors) == 0) {
    //Session
    session_start();
    //Header
    header("Content-Type:application/json");
    //Imports
    include('config.php');
    //Connection definition
    $connection = getConnection($host, $db, $user, $password);
    //retrieve the queries
    $sql = getSQL($node_id, $language, $search_keyword, $page_num, $page_size);
    $rawData = execQuery($sql, $connection);
    //response definition
    if ($rawData != null) {
        $nodes = extractNodes($rawData);
    } else {
        $nodes = [];
    }
    //response
    $response = craftResponse($nodes, null);
    $json_response = json_encode($response);
    echo $json_response;
}
//Invalid query
else {
    $response = craftResponse([], $errors);
    $json_response = json_encode($response);
    echo $json_response;
}
