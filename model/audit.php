<?php
// audit.php — đơn giản, thuần PHP, khớp bảng audit_trail hiện tại
// require_once __DIR__ . '/backend/config.php';

class AuditTrail {
  private PDO $pdo;
  private string $table;
  private array $ignore;

  public function __construct(PDO $pdo, array $options = []) {
    $this->pdo    = $pdo;
    $this->table  = $options['table'] ?? 'audit_trail';
    // Bỏ qua các field không muốn ghi diff
    $this->ignore = $options['ignore_fields'] ?? ['created_at','updated_at'];
  }

  /** UUID v4 an toàn */
  public static function uuid_v4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40); // version
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80); // variant
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
  }

  /** Lấy username hiện tại từ hệ thống của bạn */
  public static function current_user(): string {
    // ưu tiên dùng auth helper của bạn nếu có
    if (function_exists('auth_user_or_null_ui')) {
      $u = auth_user_or_null_ui();
      if (is_array($u) && !empty($u['username'])) {
        return (string)$u['username'];
      }
    }
    // fallback
    return $_SERVER['REMOTE_USER'] ?? ($_COOKIE['username'] ?? 'guest');
  }

  /** Chuẩn hoá/giới hạn reason về đúng cột VARCHAR(100) */
  private static function normalize_reason(?string $reason): string {
    $r = trim((string)$reason);
    if (function_exists('mb_strimwidth')) return mb_strimwidth($r, 0, 100, '');
    return substr($r, 0, 100);
  }

  /** Tính diff giữa dữ liệu cũ & mới */
  public function compute_diff(array|object $before, array|object $after): array {
    $b = (array)$before;
    $a = (array)$after;

    $keys = array_unique(array_merge(array_keys($b), array_keys($a)));
    $diff = [];

    foreach ($keys as $k) {
      if (in_array($k, $this->ignore, true)) continue;

      // dùng array_key_exists để không mất dấu null
      $oldExists = array_key_exists($k, $b);
      $newExists = array_key_exists($k, $a);
      $old = $oldExists ? $b[$k] : null;
      $new = $newExists ? $a[$k] : null;

      // create/delete: một bên rỗng -> log toàn bộ; update: chỉ log field khác
      $isCreate = empty($b) && !empty($a);
      $isDelete = empty($a) && !empty($b);

      if ($isCreate || $isDelete || $old !== $new) {
        $diff[$k] = ['old' => $old, 'new' => $new];
      }
    }
    return $diff;
  }

  /** Ghi 1 bản ghi audit (đúng 6 cột) */
  public function insert(string $type, string $reason, string $who, array $diff): bool {
    $sql = "INSERT INTO {$this->table}
            (id, type, reason, who, created_at, diff)
            VALUES (:id, :type, :reason, :who, NOW(), :diff)";
    $stmt = $this->pdo->prepare($sql);

    return $stmt->execute([
      'id'     => self::uuid_v4(),
      'type'   => $type,                                  // 'create' | 'update' | 'delete'
      'reason' => self::normalize_reason($reason),        // varchar(100)
      'who'    => $who,                                   // username / user_id
      'diff'   => json_encode($diff, JSON_UNESCAPED_UNICODE),
    ]);
  }


    /** Chuẩn hoá type: nhận các alias và trả về create|update|delete */
    private static function normalize_type(string $t): string {
    $t = strtolower(trim($t));
    return match ($t) {
        'create','add','insert','new'    => 'create',
        'delete','remove','del','rm'     => 'delete',
        default                          => 'update', // update, edit, patch, ...
    };
    }

    /**
     * - create: diff từ [] -> $after
     * - update: diff từ $before -> $after (không đổi thì bỏ qua)
     * - delete: diff từ $before -> []
     */
    public function log(
    string $type,
    string $reason,
    array  $before = [],
    array  $after  = [],
    ?string $who   = null
    ): bool {
    $type = self::normalize_type($type);

    if ($type === 'create') {
        $diff = $this->compute_diff([], $after);
    } elseif ($type === 'delete') {
        $diff = $this->compute_diff($before, []);
    } else { // update
        $diff = $this->compute_diff($before, $after);
        if (!$diff) return true; // không có thay đổi thì khỏi ghi log
    }

    // nếu cả before & after đều rỗng -> không có gì để log
    if (!$diff) return true;

    return $this->insert($type, $reason, $who ?? self::current_user(), $diff);
    }

}
