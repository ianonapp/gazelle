<?php

namespace Gazelle\Manager;

class Stylesheet extends \Gazelle\Base {

    protected array $info;

    public function list(): array {
        if (!isset($this->info)) {
            $stylesheets = self::$cache->get_value('stylesheets');
            if ($stylesheets === false) {
                self::$db->prepared_query("
                    SELECT ID,
                        lower(replace(Name, ' ', '_')) AS Name,
                        Name AS ProperName,
                        theme
                    FROM stylesheets
                    ORDER BY ID ASC
                ");
                $stylesheets = self::$db->to_array('ID', MYSQLI_ASSOC, false);
                self::$cache->cache_value('stylesheets', $stylesheets, 0);
            }
            $this->info = $stylesheets;
        }
        return $this->info;
    }

    public function usageList(string $orderBy, string $direction): array {
        self::$db->prepared_query("
            SELECT s.ID             AS id,
                s.Name              AS name,
                s.Description       AS description,
                s.Default           AS initial,
                s.theme,
                ifnull(ui.total, 0) AS total_enabled,
                ifnull(ud.total, 0) AS total
            FROM stylesheets AS s
            LEFT JOIN (
                SELECT StyleID,
                    count(*) AS total
                FROM users_info AS ui
                INNER JOIN users_main AS um ON (ui.UserID = um.ID)
                WHERE um.Enabled = '1'
                GROUP BY StyleID
            ) AS ui ON (s.ID = ui.StyleID)
            LEFT JOIN (
                SELECT StyleID,
                    count(*) AS total
                FROM users_info AS ui
                INNER JOIN users_main AS um ON (ui.UserID = um.ID)
                GROUP BY StyleID
            ) AS ud ON (s.ID = ud.StyleID)
            ORDER BY $orderBy $direction
        ");
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
