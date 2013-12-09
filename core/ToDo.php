<?php
/*
 * // Routes
 * -- GET / - returns all entries
 * -- GET /id - returns just one entry
 * -- POST / - adds a new entry
 * -- PUT /id + data - updates an entry
 * -- POST /auth - logs in
 * -- POST /logout - logs out
 * -- DELETE /id - deletes a specific entry
 * 
 * DB Setup:
 * CREATE TABLE `auth` (
     `id` int(11) NOT NULL AUTO_INCREMENT,
     `un` varchar(10) NOT NULL,
     `pw` varchar(32) NOT NULL,
     PRIMARY KEY (`id`),
     KEY `auth` (`un`,`pw`)
   ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;
 * CREATE TABLE `list` (
     `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
     `json` text NOT NULL,
     PRIMARY KEY (`id`)
   ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;
 * 
 * # Create a username
 * INSERT INTO `auth` (`id`, `un`, `pw`) VALUES (NULL, 'myuser', MD5('mypassword'));
 * 
 * Classes
 */

class DB {

	public $mysqli;

	public function __construct () {
		$this->mysqli = new mysqli ("localhost", "<login>", "<password>", "<db>");
		if ($this->mysqli->connect_errno) {
			echo "Failed to connect to MySQL: " . $this->mysqli->connect_error; die ();
		}
	}

}

class Entry {

	public $title;
	public $created;
	public $dueBy;
	public $priority;
	public $details;
	public $completed;

	public function __construct ($title, $dueBy, $priority, $details) {
		$this->title = $title;
		$this->created = time ();
		$this->dueBy = strtotime ($dueBy);
		$this->priority = $priority;
		$this->details = $details;
		$this->completed = false;
	}

}

class Status {

	public function show ($code) {
		switch ($code) {
			case 200:	header ("HTTP/1.1 200 Success"); break;
			case 400:	header ("HTTP/1.1 400 Bad Request"); echo "400 Bad Request"; break;
			case 401:	header ("HTTP/1.1 401 Not Authorized"); echo "401 Not Authorized"; break;
			case 404:	header ("HTTP/1.1 404 Not Found"); echo "404 Not Found"; break;
		}
	}

}

class REST {

	private $db;
	private $status;

	public function __construct () {
		$db = new DB ();
		$this->status = new Status ();
		$this->db = $db->mysqli;
		$this->router ();
	}

	public function router () {
		$route = str_replace ($_SERVER['SCRIPT_NAME'], '', $_SERVER['PHP_SELF']);
		preg_match ("/\/([0-9]+)?/", $route, $m);
		if ($m[1]) {
			$route = str_replace ('/'.$m[1], '/', $route);
			$id = $m[1];
		}
		switch ($_SERVER['REQUEST_METHOD'].' '.$route) {
			case 'GET /':		if ($this->validSession ()) {
									$this->get ($id);
								} else {
									$this->status->show (401); // no auth
								}
								break;
			case 'PUT /':		if ($this->validSession ()) {
									$entry = json_decode (file_get_contents ("php://input"));
									if ((!$id) || (!is_object ($entry))) {
										$this->status->show (400); // param missing
									} else {
										$this->put ($entry, $id);
									}
								} else {
									$this->status->show (401); // no auth
								}
								break;
			case 'POST /':		if ($this->validSession ()) {
									$entry = json_decode (file_get_contents ("php://input"));
									if (!is_object ($entry)) {
										$this->status->show (400); // param missing
									} else {
										$this->post ($entry);
									}
								} else {
									$this->status->show (401); // no auth
								}
								break;
			case 'DELETE /':	if ($this->validSession ()) {
									if (!$id) {
										$this->status->show (400); // param missing
									} else {
										$this->delete ($id);
									}
								} else {
									$this->status->show (401); // no auth
								}
								break;
			case 'POST /auth':	$un = $_POST['un'];
								$pw = $_POST['pw'];
								if ((!$un) && (!$pw)) {
									$this->status->show (400); // param missing
								} else {
									$this->auth ($un, $pw);
								}
								break;
			case 'POST /logout':
								session_start ();
								$_SESSION['auth'] = '';
								if ($_POST['next'] == 'redirect') {
									header ("HTTP/1.1 302 Moved Temporarily");
									header ("Location: /todo/index.php");
								} else {
									$this->status->show (200);
								}
								break;
			default:			$this->status->show (404); // route not recognised
		}
	}

	public function get ($id = '') {
		$this->status->show (200);
		header ("Content-Type: application/json; charset=utf-8");
		if ($id) {
			$sql = "SELECT json FROM list WHERE id = '$id'";
			$res = $this->db->query ($sql);
			$row = $res->fetch_assoc ();
			if ($row) {
				$rowJson = json_decode ($row['json']);
				$rowJson->dueBy = date ("Y-m-d H:i:s", $rowJson->dueBy);
				$rowJson->id = $id;
				echo "[";
				echo json_encode ($rowJson);
				echo "]";
			} else {
				$this->status->show (404); // id not found
			}
		} else {
			$sql = "SELECT id, json FROM list";
			$res = $this->db->query ($sql);
			$i = 0;
			echo "[";
			while ($row = $res->fetch_assoc ()) {
				$rowJson = json_decode ($row['json']);
				$rowJson->dueBy = date ("Y-m-d H:i:s", $rowJson->dueBy);
				$rowJson->id = $row['id'];
				echo json_encode ($rowJson).($i + 1 < $res->num_rows ? "," : ""); $i++;
			}
			echo "]";
		}
	}

	public function put ($entry, $id) {
		$sql = "SELECT json FROM list WHERE id = '$id'";
		$res = $this->db->query ($sql);
		$row = $res->fetch_assoc ();
		if ($row) {
			$entry->dueBy = strtotime ($entry->dueBy);
			$sql = "UPDATE list SET json = '".addslashes (json_encode ($entry))."' WHERE id = '$id'";
			$this->db->query ($sql);
			$this->status->show (200);
		} else {
			$this->status->show (404); // id not found
		}
	}

	public function post ($entry) {
		$entry->dueBy = strtotime ($entry->dueBy);
		$sql = "INSERT INTO list (json) values ('".addslashes (json_encode ($entry))."')";
		$this->db->query ($sql);
		$entry->id = $entry->_id = $this->db->insert_id;
		if ($entry->id) {
			$this->status->show (200);
			$entry->dueBy = date ("Y-m-d H:i:s", $entry->dueBy);
			echo json_encode ($entry); // output original
		} else {
			$this->status->show (404); // error
		}
	}

	public function delete ($id) {
		$sql = "DELETE FROM list WHERE id = '$id'";
		$this->db->query ($sql);
		if ($this->db->affected_rows) {
			$this->status->show (200);
		} else {
			$this->status->show (404); // id not found
		}
	}

	public function auth ($un, $pw) {
		$pwx = md5 ($pw);
		$sql = "SELECT id, un FROM auth WHERE un = '".addslashes ($un)."' AND pw = '".addslashes ($pwx)."'";
		$res = $this->db->query ($sql);
		$row = $res->fetch_assoc ();
		if ($row['id']) {
			session_start ();
			$_SESSION['auth'] = $row['id'];
			if ($_POST['next'] == 'redirect') {
				header ("HTTP/1.1 302 Moved Temporarily");
				header ("Location: /todo/index.php");
			} else {
				$this->status->show (200);
			}
		} else {
			$this->status->show (404); // auth not found
			if ($_POST['next'] == 'redirect') {
				echo "Sorry, you could not be authorised.";
			}
		}
	}

	public function validSession () {
		session_start ();
		if (!$_SESSION['auth']) {
			return false;
		} else {
			$sql = "SELECT un FROM auth WHERE id = '".$_SESSION['auth']."'";
			$res = $this->db->query ($sql);
			$row = $res->fetch_assoc ();
			return $row['un'];
		}
	}

}

$rest = new REST ();
?>
