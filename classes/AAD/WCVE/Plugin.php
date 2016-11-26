<?php

/*
 * Copyright (C) 2016 Kenneth J. Brucker <ken.brucker@action-a-day.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


/**
 * Plugin Container
 * 
 * @package woocommerce-variation-editor
 * @author Kenneth J. Brucker <ken.brucker@action-a-day.com>
 */
/*
 *  Protect from direct execution
 */
if ( !defined( 'WP_PLUGIN_DIR' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die( 'I don\'t think you should be here.' );
}

class AAD_WCVE_Plugin implements ArrayAccess {
	protected $contents;
	
  public function __construct() {
    $this->contents = array();
  }
  
  public function offsetSet( $offset, $value ) {
    $this->contents[$offset] = $value;
  }

  public function offsetExists($offset) {
    return isset( $this->contents[$offset] );
  }

  public function offsetUnset($offset) {
    unset( $this->contents[$offset] );
  }

  public function offsetGet($offset) {
    if( is_callable($this->contents[$offset]) ){
      return call_user_func( $this->contents[$offset], $this );
    }
    return isset( $this->contents[$offset] ) ? $this->contents[$offset] : null;
  }
  
  public function run(){ 
    foreach( $this->contents as $key => $content ){ // Loop on contents
      if( is_callable($content) ){
        $content = $this[$key];
      }
      if( is_object( $content ) ){
        $reflection = new ReflectionClass( $content );
        if( $reflection->hasMethod( 'run' ) ){
          $content->run(); // Call run method on object
        }
      }
    }
  }
}
