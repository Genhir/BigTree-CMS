<?php
	/*
		Class: BigTreeSessionHandler
			A session handler for storing BigTree sessions in the database.
	*/

	class BigTreeSessionHandler {

		private static $Exists = false;
		private static $Timeout = 3600;

		// These aren't needed as the SQL class handles the connection
		static function open() { return true; }
		static function close() { return true; }

		static function read($id) {
			$session = SQL::fetch("SELECT * FROM bigtree_sessions WHERE id = ?", $id);

			if (!$session) {
				return "";
			}

			static::$Exists = true;

			// Invalidate a session that is too old's data
			if ($session["last_accessed"] < time() - static::$Timeout) {
				SQL::update("bigtree_sessions", $id, ["data" => "", "last_accessed" => time()]);

				return "";
			} else {
				SQL::update("bigtree_sessions", $id, ["last_accessed" => time()]);

				return $session["data"];
			}
		}

		static function write($id, $data) {
			if (!static::$Exists) {
				SQL::query("INSERT INTO bigtree_sessions (`id`, `last_accessed`, `data`) VALUES (?, ?, ?)", $id, time(), $data);
			} else {
				SQL::update("bigtree_sessions", $id, ["last_accessed" => time(), "data" => $data]);
			}

			return true;
		}

		static function destroy($id) {
			return SQL::delete("bigtree_sessions", $id);
		}

		static function clean($max_age) {
			SQL::query("DELETE FROM bigtree_sessions WHERE last_accessed < ?", time() - $max_age);

			return true;
		}

		static function start() {
			global $bigtree;

			if (!empty($bigtree["config"]["session_lifetime"])) {
				static::$Timeout = intval($bigtree["config"]["session_lifetime"]);
			}

			if (!empty($bigtree["config"]["session_handler"]) && $bigtree["config"]["session_handler"] == "db") {
				session_set_save_handler(
					"BigTreeSessionHandler::open",
					"BigTreeSessionHandler::close",
					"BigTreeSessionHandler::read",
					"BigTreeSessionHandler::write",
					"BigTreeSessionHandler::destroy",
					"BigTreeSessionHandler::clean"
				);
			}

			session_set_cookie_params(0, str_replace(DOMAIN, "", WWW_ROOT), "", false, true);
			session_start(array("gc_maxlifetime" => static::$Timeout));
		}

	}
