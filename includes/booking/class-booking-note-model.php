<?php
namespace VandelBooking\Booking;

/**
 * Note Model
 */
class NoteModel {
    /**
     * @var string Table name
     */
    private $table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'vandel_booking_notes';
    }
    
    /**
     * Add booking note
     * 
     * @param int $booking_id Booking ID
     * @param string $note_content Note content
     * @param int $user_id User ID (0 for system)
     * @return int|false Note ID or false if failed
     */
    public function addNote($booking_id, $note_content, $user_id = 0) {
        global $wpdb;
        
        if (empty($note_content)) {
            return false;
        }
        
        $user_id = $user_id ?: get_current_user_id();
        
        $result = $wpdb->insert(
            $this->table,
            [
                'booking_id' => $booking_id,
                'note_content' => $note_content,
                'created_at' => current_time('mysql'),
                'created_by' => $user_id
            ],
            ['%d', '%s', '%s', '%d']
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get booking notes
     * 
     * @param int $booking_id Booking ID
     * @return array Notes
     */
    public function getNotes($booking_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as user_name 
             FROM {$this->table} n 
             LEFT JOIN {$wpdb->users} u ON n.created_by = u.ID 
             WHERE booking_id = %d 
             ORDER BY created_at DESC",
            $booking_id
        ));
    }
    
    /**
     * Delete booking note
     * 
     * @param int $note_id Note ID
     * @param int $booking_id Booking ID
     * @param int $user_id User ID (0 to ignore user check)
     * @return bool Whether the note was deleted
     */
    public function deleteNote($note_id, $booking_id, $user_id = 0) {
        global $wpdb;
        
        $where = [
            'id' => $note_id,
            'booking_id' => $booking_id
        ];
        
        $where_format = ['%d', '%d'];
        
        // Only allow users to delete their own notes
        if ($user_id > 0) {
            $where['created_by'] = $user_id;
            $where_format[] = '%d';
        }
        
        $result = $wpdb->delete(
            $this->table,
            $where,
            $where_format
        );
        
        return $result !== false;
    }
}