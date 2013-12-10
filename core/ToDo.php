<?php
/*
 * // Routes
 * -- GET / - returns all entries
 * -- GET /id - returns just one entry
 * -- POST / - adds a new entry
 * -- PUT /id + data - updates an entry
 * -- POST /auth - logs in
 * -- POST /logout - logs out
 * -- POST /register - creates a new account
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

	public function show ($code, $redirect = '') {
		switch ($code) {
			case 200:	header ("HTTP/1.1 200 Success"); break;
			case 302:	header ("HTTP/1.1 302 Moved Temporarily"); header ("Location: ".$redirect); break;
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
									$this->status->show (302, '/todo/index.php');
								} else {
									$this->status->show (200);
								}
								break;
			case 'POST /register':
								$un = $_POST['un'];
								$pw = $_POST['pw'];
								if ((!$un) && (!$pw)) {
									$this->status->show (400); // param missing
								} else {
									$this->register ($un, $pw);
								}
								break;
			default:			$this->status->show (404); // route not recognised
		}
	}

	public function get ($id = '') {
		$this->status->show (200);
		header ("Content-Type: application/json; charset=utf-8");
		if ($id) {
			if ($stmt = $this->db->prepare ("SELECT json FROM list WHERE id = ?")) {
				$stmt->bind_param ("i", $id);
				$stmt->execute ();
				$stmt->bind_result ($json);
				$stmt->fetch ();
				$stmt->close ();
			}
			if ($json) {
				$rowJson = json_decode ($json);
				$rowJson->dueBy = date ("Y-m-d H:i:s", $rowJson->dueBy);
				$rowJson->title = htmlspecialchars ($rowJson->title);
				$rowJson->details = htmlspecialchars ($rowJson->details);
				$rowJson->id = $id;
				echo "[";
				echo json_encode ($rowJson);
				echo "]";
			} else {
				$this->status->show (404); // id not found
			}
		} else {
			if ($stmt = $this->db->prepare ("SELECT id, json FROM list")) {
				$stmt->execute ();
				$stmt->bind_result ($id, $json);
				$i = 0;
				echo "[";
				while ($stmt->fetch ()) {
					if ($i) {
						echo ',';
					}
					$rowJson = json_decode ($json);
					$rowJson->dueBy = date ("Y-m-d H:i:s", $rowJson->dueBy);
					$rowJson->title = htmlspecialchars ($rowJson->title);
					$rowJson->details = htmlspecialchars ($rowJson->details);
					$rowJson->id = $id;
					echo json_encode ($rowJson);
					$i++;
				}
				echo "]";
				$stmt->close ();
			}
		}
	}

	public function put ($entry, $id) {
		if ($stmt = $this->db->prepare ("SELECT json FROM list WHERE id = ?")) {
			$stmt->bind_param ("i", $id);
			$stmt->execute ();
			$stmt->bind_result ($json);
			$stmt->fetch ();
			$stmt->close ();
		}
		if ($json) { // exists...
			$entry->dueBy = strtotime ($entry->dueBy);
			if ($stmt = $this->db->prepare ("UPDATE list SET json = ? WHERE id = ?")) {
				$stmt->bind_param ("si", json_encode ($entry), $id);
				$stmt->execute ();
				$stmt->close ();
			}
			$this->status->show (200);
		} else {
			$this->status->show (404); // id not found
		}
	}

	public function post ($entry) {
		$entry->dueBy = strtotime ($entry->dueBy);
		if ($stmt = $this->db->prepare ("INSERT INTO list (json) VALUES (?)")) {
			$stmt->bind_param ("s", json_encode ($entry));
			$stmt->execute ();
			$id = $stmt->insert_id;
			$stmt->close ();
		}
		if ($id) {
			$this->status->show (200);
			$entry->dueBy = date ("Y-m-d H:i:s", $entry->dueBy);
			$entry->title = htmlspecialchars ($entry->title);
			$entry->details = htmlspecialchars ($entry->details);
			$entry->id = $entry->_id = $id;
			header ("Content-Type: application/json; charset=utf-8");
			echo json_encode ($entry); // output original
		} else {
			$this->status->show (404); // error
		}
	}

	public function delete ($id) {
		if ($stmt = $this->db->prepare ("DELETE FROM list WHERE id = ?")) {
			$stmt->bind_param ("i", $id);
			$stmt->execute ();
			$deleted = $stmt->affected_rows;
			$stmt->close ();
		}
		if ($deleted) {
			$this->status->show (200);
		} else {
			$this->status->show (404); // id not found
		}
	}

	public function auth ($un, $pw) {
		if ($stmt = $this->db->prepare ("SELECT id, un FROM auth WHERE un = ? AND pw = ?")) {
			$stmt->bind_param ("ss", $un, md5 ($pw));
			$stmt->execute ();
			$stmt->bind_result ($id, $un);
			$stmt->fetch ();
			$stmt->close ();
		}
		if ($id) {
			session_start ();
			$_SESSION['auth'] = $id;
			if ($_POST['next'] == 'redirect') {
				$this->status->show (302, '/todo/index.php');
			} else {
				$this->status->show (200);
			}
		} else {
			if ($_POST['next'] == 'redirect') {
				echo "Sorry, you could not be authorised.";
			} else {
				$this->status->show (404); // auth not found
			}
		}
	}

	public function register ($un, $pw) {
		if ($stmt = $this->db->prepare ("SELECT id, un FROM auth WHERE un = ?")) {
			$stmt->bind_param ("s", $un);
			$stmt->execute ();
			$stmt->bind_result ($id, $un);
			$stmt->fetch ();
			$stmt->close ();
		}
		if ($id) {
			$this->status->show (400); // already registered
		} else {
			if ($stmt = $this->db->prepare ("INSERT INTO auth (id, un, pw) VALUES (NULL, ?, ?)")) {
				$stmt->bind_param ("ss", $un, md5 ($pw));
				$stmt->execute ();
				$id = $stmt->insert_id;
				$stmt->close ();
			}
			if ($id) {
				session_start ();
				$_SESSION['auth'] = $id;
				if ($_POST['next'] == 'redirect') {
					$this->status->show (302, '/todo/index.php');
				} else {
					$this->status->show (200);
				}
			} else {
				$this->status->show (400); // error
			}
		}
	}

	public function validSession () {
		session_start ();
		if (!$_SESSION['auth']) {
			return false;
		} else {
			if ($stmt = $this->db->prepare ("SELECT un FROM auth WHERE id = ?")) {
				$stmt->bind_param ("i", $_SESSION['auth']);
				$stmt->execute ();
				$stmt->bind_result ($un);
				$stmt->fetch ();
				$stmt->close ();
			}
			return $un;
		}
	}

}

new REST ();
?>
