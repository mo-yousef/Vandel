<?php
namespace VandelBooking\PostTypes;

/**
 * Post Types Registry
 * 
 * A central class to register all custom post types
 */
class Registry {
    /**
     * Instance of ServicePostType
     * 
     * @var ServicePostType
     */
    private $service_post_type;
    
    /**
     * Instance of SubServicePostType
     * 
     * @var SubServicePostType
     */
    private $sub_service_post_type;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize post types
     */
    public function init() {
        $this->service_post_type = new ServicePostType();
        $this->sub_service_post_type = new SubServicePostType();
    }
    
    /**
     * Get ServicePostType instance
     * 
     * @return ServicePostType
     */
    public function getServicePostType() {
        return $this->service_post_type;
    }
    
    /**
     * Get SubServicePostType instance
     * 
     * @return SubServicePostType
     */
    public function getSubServicePostType() {
        return $this->sub_service_post_type;
    }
}
