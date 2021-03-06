<?php

/* 
 * class to handle all db operations
 * all the CRUD operations on database
 */

class DbHandler {
    
    private $conn;
    
    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        
        $db = new DbConnect();
        $this->conn = $db->connect();
    }
    
    //-----------------------------------------------------------------
    // TABLE: user-regular
    
    /*
     * creating new user
     */
    
    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';
        $response = [];
        
        // check if user email alrready exists
        if ($this->isUserExists($email)) {
            return USER_ALREADY_EXISTED;
        } else {
            // generate password hash
            $password_hash = PassHash::hash($password);
            
            // generate api key
            $api_key = $this->generateApiKey();
            
            // insert query 
            $stmt = $this->conn->prepare("INSERT INTO user_regular 
                (name, email, password_hash, api_key)
                VALUES (?, ?, ?, ?);");
            
            $stmt->bind_param("ssss", $name, $email, $password_hash, $api_key);
            
            $result = $stmt->execute();
            
            if ($result) {
                return USER_CREATED_SUCCESSFULLY;
            } else {
                return USER_CREATED_FAILED;
            }
        }
        
        return $response;
    }
    
    /*
     * check if user exists
     */
    public function isUserExists($email) {
        $stmt = $this->conn->prepare("SELECT * FROM user_regular WHERE email = ?;");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows>0;
    }
    
    /*
     * validating user api key
     */
    public function isValidApiKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id_user_regular FROM user_regular WHERE api_key = ?;");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /*
     * generate api key
     */
    public function generateApiKey() {
        return md5(uniqid(rand(), true));
    }
    
    /*
     * get user id by api key
     */
    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("SELECT id_user_regular FROM user_regular WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $user_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }
    
    /*
     * checking user login
     */
    public function checkLogin($email, $password) {
        $stmt = $this->conn->prepare('SELECT password_hash FROM user_regular WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->fetch();
            $stmt->close();
            
            if (PassHash::check_password($password_hash, $password)) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            $stmt->close();
            return FALSE;
        }
    }
    
    /*
     * get user id 
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM user_regular WHERE email = ?;");
        $stmt->bind_param("s", $email);
        
        if ($stmt->execute()) {
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }
    
    /*
     *  get all tvseries
     *  should also give back whether in collection or not
     */
    public function getAllTvSeries($user_id) {
        $stmt = $this->conn->prepare("SELECT tv_series.id_tv_series, tv_series.tv_series_name, tv_series.count_like, tv_series.count_rating, tv_series.default_image, collection.status, collection.id_user_regular
                        FROM tv_series
                        LEFT JOIN collection on collection.id_tv_series = tv_series.id_tv_series AND collection.id_user_regular = ?;");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tv_series_list = $stmt->get_result();
        $stmt->close();
        return $tv_series_list;
    }
    
    /*
     *  get collection of a single user
     */
    public function getCollection($user_id) {
        $stmt = $this->conn->prepare("SELECT tv_series.id_tv_series, tv_series.tv_series_name, tv_series.count_like, tv_series.count_rating, tv_series.default_image, collection.id_user_regular
                FROM tv_series
                LEFT JOIN collection on collection.id_tv_series = tv_series.id_tv_series 
                WHERE collection.id_user_regular = ?;");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $tv_series_list = $stmt->get_result();
        $stmt->close();
        return $tv_series_list;
    }
    
    /*
     *  add TV_SERIES to collection
     */
    public function addTvSeriesToCollection($id_tv_series, $id_user) {
        $stmt = $this->conn->prepare("INSERT INTO collection (`id_user_regular`, `id_tv_series`)
            VALUES (?, ?);");
        $stmt->bind_param("ii", $id_user, $id_tv_series);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * get single tv_series
     */
    public function getSingleTvSeries($id_tv_series) {
        $stmt = $this->conn->prepare("SELECT id_tv_series, tv_series_name, imdb_link, count_like, count_rating, default_image FROM tv_series "
                . "WHERE id_tv_series = ?;");
        $stmt->bind_param("i", $id_tv_series);
        if ($stmt->execute()) {
            $tv_series = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $tv_series;
        } else {
            return NULL;
        }
    }
    
    /* is like available */
    public function isLikeAvailable($id_photo, $id_user) {
        $stmt = $this->conn->prepare("SELECT id_like FROM like_photo WHERE id_photo = ? AND id_user_regular = ?;");
        $stmt->bind_param("ii", $id_photo, $id_user);
        if ($stmt->execute()) {
            $like_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $like_id;
        } else {
            return NULL;
        }
    }

    /* add like */
    public function addLike($id_photo, $id_user) {
        $stmt = $this->conn->prepare("INSERT INTO like_photo
                    (`id_photo`, `id_user_regular`)
                    VALUES (?, ?);");
        $stmt->bind_param("ii", $id_photo, $id_user);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /* remove like */
    public function removeLike($id_like) {
        $stmt = $this->conn->prepare("DELETE FROM like_photo
                WHERE id_like = ?;");
        $stmt->bind_param("i", $id_like);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /* increase like by 1 */
    public function incrementLike($id_photo) {
        $stmt = $this->conn->prepare("UPDATE photo 
                SET count_like = count_like + 1
                WHERE id_photo = ?;");
        $stmt->bind_param("i", $id_photo);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /* decrese like by 1 */
    public function decrementLike($id_photo) {
        $stmt = $this->conn->prepare("UPDATE photo 
                SET count_like = count_like - 1
                WHERE id_photo = ?
                and count_like > 0;");
        $stmt->bind_param("i", $id_photo);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /* is rating available */
    public function isRatingAvailable($id_tv_series, $id_user) {
        $stmt = $this->conn->prepare("SELECT id_rating FROM rating WHERE id_tv_series = ? AND id_user_regular = ?;");
        $stmt->bind_param("ii", $id_tv_series, $id_user);
        if ($stmt->execute()) {
            $rating_id = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $rating_id;
        } else {
            return NULL;
        }
    }

    /* add rating */
    public function addRating($id_tv_series, $id_user, $value_rating) {
        $stmt = $this->conn->prepare("INSERT INTO rating
                    (`id_tv_series`, `id_user_regular`, `value_rating`)
                    VALUES (?, ?, ?);");
        $stmt->bind_param("iii", $id_tv_series, $id_user, $value_rating);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /* update rating */
    public function updateRating($id_rating, $rating_value) {
        $stmt = $this->conn->prepare("UPDATE rating
                    SET `value_rating` = ?
                    WHERE `id_rating` = ?;");
        $stmt->bind_param("ii",$rating_value, $id_rating);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /* change the values of rating of a tv_series */
    public function changeRatingValueOfTvSeries($id_tv_series, $new_rating) {
        $stmt = $this->conn->prepare("UPDATE tv_series 
                SET count_rating = (count_rating * count_rating_giver) - 1,
                count_rating_giver
                WHERE id_photo = ?
                and count_like > 0;");
        $stmt->bind_param("i", $id_photo);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}