<?php
/**
 * Plugin Name:       Find Face
 * Description:       Upload a picture of some person and find posts or pages containing images of that person via face recognition.
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            Solomon Schimura
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       find_face
 *
 */

$TOLERANCE = 0.5;
$CONTAINS_FACE = "__ff_contains_face__";
$RECOGNITION_MODEL = __DIR__ . "/dlib_face_recognition_resnet_model_v1.dat";
$LANDMARK_MODEL = __DIR__ . "/shape_predictor_5_face_landmarks.dat";
$DETECTION_MODEL = __DIR__ . "/mmod_human_face_detector.dat";
$LOGGING = true;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function ff_log_message($val) {
    global $LOGGING;
    if ( !$LOGGING ) return;
    if ( is_object($val) ) {
        $val = $val->to_array();
    }
    file_put_contents('php://stderr', (is_array($val) ? implode(',', array_keys($val)) : $val) . "\n");
}

function ff_compute_descriptor( $image ) {
    global $DETECTION_MODEL;
    global $RECOGNITION_MODEL;
    global $LANDMARK_MODEL;

    $fd = new CnnFaceDetection($DETECTION_MODEL);
    $faces = $fd->detect($image);

    if (sizeof($faces) <= 0) {
        ff_log_message("No face found in image: " . $image);
        return false;
    }
    if (sizeof($faces) > 1) {
        ff_log_message("Please make sure there is only one person in the image: " . $image);
        return false;
    }
    
    $fld = new FaceLandmarkDetection( $LANDMARK_MODEL );
    $fr = new FaceRecognition( $RECOGNITION_MODEL );

    return $fr->computeDescriptor( $image, $fld->detect($image, $faces[0]) );
}

function ff_compare( $left, $right ) {
    global $TOLERANCE;

    $eucl = 0;
    for($i = 0; $i < 128; $i++) {
        $eucl += pow($left[$i] - $right[$i], 2);
    }
    $eucl = sqrt($eucl);
    ff_log_message("distance found: " . $eucl);

    return $eucl < $TOLERANCE;
}

function ff_search( $search, $callback ) {
    global $CONTAINS_FACE;

    $posts_found = array();
    $desc = ff_compute_descriptor( $search );

    if ( $desc ) {
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'numberposts' => -1,
        ));
        $media_posts = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => -1,
        ));
        if ($posts && $media_posts) {
            foreach ($posts as $post) {

                foreach ($media_posts as $media_post) {
                    $post_meta = get_post_meta($media_post->ID);
                    
                    if ( ! array_key_exists($CONTAINS_FACE, $post_meta) ) {
                        continue;
                    }
                    $attachment_meta = wp_get_attachment_metadata( $media_post->ID );
                    $file_name = strrchr( $attachment_meta["file"], "/", false );
                    $file_path = strrchr( get_attached_file( $media_post->ID ), "/", true );
                    $file_base = strrchr( $file_path, "wp-content", false ) . "/" . $media_post->post_title;

                    // check whether attachment is referenced in post content
                    if ( !str_contains($post->post_content, $file_base) ) {
                        continue;
                    }

                    $other_desc = unserialize($post_meta[$CONTAINS_FACE][0]);

                    if ( ! $other_desc ) {
                        continue;
                    }

                    if ( ! ff_compare( $desc, $other_desc ) ) {
                        continue;
                    }

                    if ( ! in_array( $post->ID, $posts_found) ) {
                        $posts_found[] = $post->post_title;
                    }
                }
            }
        }
    }
    $callback( $posts_found );
}

function ff_mark_attachment( $id, $post, $before ) {
    global $CONTAINS_FACE;

    if ( ! $post->post_excerpt == $CONTAINS_FACE ) {
        return;
    }

    update_post_meta( $id, $CONTAINS_FACE, unserialize($post->post_content) );

    wp_update_post(array(
        'ID' => $id,
        'post_excerpt' => '',
        'post_content' => ''
    ));
}

function ff_mark_post( $id ) {
    global $CONTAINS_FACE;

    if ( ! str_starts_with( get_post_mime_type($id), "image") ) {
        return;
    }

    $file = get_post_meta( $id )["_wp_attached_file"][0];

    $desc = ff_compute_descriptor( wp_upload_dir()["basedir"] . "/" . $file );
    if ( !$desc ) {
        return;
    }

    wp_update_post(array(
        'ID' => $id,
        'post_excerpt' => $CONTAINS_FACE,
        'post_content' => serialize($desc)
    ));
}

add_action("add_attachment", "ff_mark_post", 10, 1);
add_action("attachment_updated", "ff_mark_attachment", 10, 3);
add_action("search_face", "ff_search", 10, 2);