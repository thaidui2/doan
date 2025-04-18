<?php
/**
 * Settings Manager Class
 * Manages system settings across multiple tables with various data types
 */
class SettingsManager {
    private $conn;
    private $settings = [];
    private $existingKeys = [];
    
    /**
     * Constructor
     * @param mysqli $conn Database connection
     */
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadExistingKeys();
    }
    
    /**
     * Load all existing setting keys to avoid collation issues
     */
    private function loadExistingKeys() {
        $query = "SELECT khoa FROM cai_dat";
        $result = $this->conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->existingKeys[] = $row['khoa'];
            }
        }
    }
    
    /**
     * Load all settings from both tables
     * @return array Settings organized by group
     */
    public function loadAllSettings() {
        // Get settings from cai_dat table (preferred)
        $settings = [];
        $query = "SELECT nhom as group_name, khoa as key_name, gia_tri as value, 
                 kieu_du_lieu as type, mo_ta as description 
                 FROM cai_dat ORDER BY nhom, id";
        
        $result = $this->conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $group = $row['group_name'];
                $key = $row['key_name'];
                $settings[$group][$key] = $row;
            }
        }
        
        // Get settings from legacy settings table without collation issues
        $query2 = "SELECT setting_group as group_name, setting_key as key_name, 
                  setting_value as value, 'string' as type, '' as description 
                  FROM settings 
                  ORDER BY setting_group, id";
        
        $result2 = $this->conn->query($query2);
        if ($result2) {
            while ($row = $result2->fetch_assoc()) {
                $group = $row['group_name'];
                $key = $row['key_name'];
                // Skip if this key already exists in cai_dat
                if (!in_array($key, $this->existingKeys) && !isset($settings[$group][$key])) {
                    $settings[$group][$key] = $row;
                }
            }
        }
        
        $this->settings = $settings;
        return $settings;
    }
    
    /**
     * Update a setting value
     * @param string $key Setting key
     * @param string $value Setting value
     * @return bool Success status
     */
    public function updateSetting($key, $value) {
        // First try updating in cai_dat table
        $stmt = $this->conn->prepare("UPDATE cai_dat SET gia_tri = ? WHERE khoa = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
        
        // If no rows affected, check if we need to insert or update settings table
        if ($stmt->affected_rows === 0) {
            // Check if key exists in cai_dat
            if (in_array($key, $this->existingKeys)) {
                // The key exists but didn't update (value unchanged?)
                return true;
            }
            
            // Try to update in settings table
            $stmt2 = $this->conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt2->bind_param("ss", $value, $key);
            $stmt2->execute();
            
            if ($stmt2->affected_rows === 0) {
                // Try to insert if it doesn't exist in either table
                $group = $this->guessSettingGroup($key);
                $stmt3 = $this->conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)");
                $stmt3->bind_param("sss", $key, $value, $group);
                $stmt3->execute();
                return $stmt3->affected_rows > 0;
            }
            
            return $stmt2->affected_rows > 0;
        }
        
        return true;
    }
    
    /**
     * Guess which group a setting belongs to based on its key
     * @param string $key Setting key
     * @return string Group name
     */
    private function guessSettingGroup($key) {
        $key = strtolower($key);
        
        if (strpos($key, 'smtp_') === 0 || strpos($key, 'email') !== false) {
            return 'email';
        } elseif (strpos($key, 'shipping') !== false || strpos($key, 'delivery') !== false) {
            return 'shipping';
        } elseif (strpos($key, 'payment') !== false || strpos($key, 'bank') !== false || strpos($key, 'cod') !== false) {
            return 'payment';
        } elseif (strpos($key, 'facebook') !== false || strpos($key, 'twitter') !== false || 
                 strpos($key, 'instagram') !== false || strpos($key, 'youtube') !== false) {
            return 'social';
        } elseif (strpos($key, 'contact') !== false || strpos($key, 'phone') !== false || strpos($key, 'address') !== false) {
            return 'contact';
        } elseif (strpos($key, 'order') !== false || strpos($key, 'checkout') !== false) {
            return 'order';
        } else {
            return 'general'; // Default group
        }
    }
    
    /**
     * Handle file upload for settings
     * @param array $file File data from $_FILES
     * @param string $target_dir Target directory
     * @return array [success, filename|error_message]
     */
    public function uploadFile($file, $target_dir) {
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $filename = time() . '_' . basename($file['name']);
        $target_file = $target_dir . $filename;
        
        // Check file format
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico');
        
        if (!in_array($file_ext, $allowed_exts)) {
            return [false, "Chỉ chấp nhận file ảnh: " . implode(', ', $allowed_exts)];
        }
        
        // Check file size (2MB limit)
        if ($file['size'] > 2 * 1024 * 1024) {
            return [false, "File không được vượt quá 2MB"];
        }
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return [true, $filename];
        } else {
            return [false, "Không thể upload file: " . error_get_last()['message']];
        }
    }
    
    /**
     * Save all settings from form submission
     * @param array $post_data POST data
     * @param array $files FILES data
     * @return bool Success status
     */
    public function saveSettings($post_data, $files) {
        $this->conn->begin_transaction();
        
        try {
            // Update text settings
            foreach ($post_data['setting'] as $key => $value) {
                $key = trim($key);
                $value = is_array($value) ? implode(',', $value) : trim($value);
                
                // Special handling for empty checkboxes (which don't send any value)
                if (strpos($key, 'enable_') === 0 || 
                    in_array($key, ['cod_enabled', 'bank_transfer_enabled']) ||
                    $this->getSettingType($key) === 'boolean') {
                    if (!isset($post_data['setting'][$key])) {
                        $value = '0';
                    }
                }
                
                $this->updateSetting($key, $value);
            }
            
            // Handle file uploads
            if (!empty($files['logo']['name'])) {
                $logo_info = $this->uploadFile($files['logo'], '../uploads/general/');
                if ($logo_info[0]) {
                    $logo_path = 'uploads/general/' . $logo_info[1];
                    $this->updateSetting('logo', $logo_path);
                } else {
                    throw new Exception($logo_info[1]);
                }
            }
            
            if (!empty($files['favicon']['name'])) {
                $favicon_info = $this->uploadFile($files['favicon'], '../uploads/general/');
                if ($favicon_info[0]) {
                    $favicon_path = 'uploads/general/' . $favicon_info[1];
                    $this->updateSetting('favicon', $favicon_path);
                } else {
                    throw new Exception($favicon_info[1]);
                }
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Determine the data type of a setting based on its key
     * @param string $key Setting key
     * @return string Data type (string, boolean, number, email, etc)
     */
    public function getSettingType($key) {
        // Known boolean settings
        $boolean_keys = ['enable_', 'cod_enabled', 'bank_transfer_enabled', 'smtp_auth', 'is_', 'has_', 'show_'];
        foreach ($boolean_keys as $prefix) {
            if (strpos($key, $prefix) === 0) {
                return 'boolean';
            }
        }
        
        // Known number settings
        $number_keys = ['_price', '_cost', '_fee', '_limit', '_min', '_max', '_count', '_amount', '_threshold'];
        foreach ($number_keys as $suffix) {
            if (strpos($key, $suffix) !== false) {
                return 'number';
            }
        }
        
        // Known email settings
        if (strpos($key, 'email') !== false || strpos($key, 'smtp_user') !== false) {
            return 'email';
        }
        
        // Text area settings
        if (strpos($key, 'description') !== false || strpos($key, 'address') !== false || 
            strpos($key, 'content') !== false || strpos($key, 'message') !== false) {
            return 'textarea';
        }
        
        // Default to string
        return 'string';
    }
    
    /**
     * Get a specific setting value
     * @param string $key Setting key
     * @param string $default Default value if setting not found
     * @return mixed Setting value or default
     */
    public function getSetting($key, $default = '') {
        // Try to get from cai_dat table
        $stmt = $this->conn->prepare("SELECT gia_tri FROM cai_dat WHERE khoa = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['gia_tri'];
        }
        
        // Try to get from settings table
        $stmt2 = $this->conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt2->bind_param("s", $key);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        if ($result2->num_rows > 0) {
            $row = $result2->fetch_assoc();
            return $row['setting_value'];
        }
        
        return $default;
    }
    
    /**
     * Generate human-friendly label from setting key
     * @param string $key Setting key
     * @return string Human-friendly label
     */
    public function generateLabel($key) {
        $label = str_replace(['_', '-'], ' ', $key);
        return ucwords($label);
    }
    
    /**
     * Render form field based on setting type
     */
    public function renderField($key, $setting) {
        // ... existing code ...
        return ''; // Default output if no matching type
    }
}
