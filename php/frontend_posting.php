<?php
namespace SIM\SIGNAL;
use SIM;

add_action('sim_frontend_post_after_content', __NAMESPACE__.'\afterContent');
function afterContent($frontendContend){
    $hidden	= 'hidden';
    if(
        $frontendContend->fullrights &&                             // we have publish rights
        (
            $frontendContend->postId == null ||                     // this is a new page
            !empty($frontendContend->getPostMeta('send_signal'))    // we should send a signal message
        )
    ){
        $checked 	    = 'checked';
        $hidden		    = '';
        $messageType	= $frontendContend->getPostMeta('signal_message_type');
    }

    ?>
    <div id="signal-message" class="frontend-form">
        <h4>Signal</h4>
        <label>
            <input type='checkbox' name='send-signal' value='1' <?php echo $checked; ?>>
            Send signal message on <?php echo $frontendContend->update ? 'update' : 'publish';?>
        </label>

        <div class='signal-message-type <?php echo $hidden;?>' style='margin-top:15px;'>
            <label>
                <input type='radio' name='signal-message-type' value='summary' <?php if($messageType != 'all'){echo 'checked';}?>>
                Send a summary
            </label>
            <label>
                <input type='radio' name='signal-message-type' value='all' <?php if($messageType == 'all'){echo 'checked';}?>>
                Send the whole post content
            </label>
            <br>
            <br>
            <label>
                Add this sentence to the signal message:<br>
                <input type="text" name="signal-extra-message">
            </label>
            <br>
            <br>
            <label>
                <input type="checkbox" name="signal-url" value='1'>
                Include the url in the message even if the whole content is posted
            </label>
        </div>
    </div>
    <?php
}

// Send Signal message about the new or updated post
add_action('sim_after_post_save', __NAMESPACE__.'\afterPostSave', 999);
function afterPostSave($post){
    if(isset($_POST['send-signal']) && $_POST['send-signal']){
        update_metadata( 'post', $post->ID, 'send_signal', true);
        update_metadata( 'post', $post->ID, 'signal_message_type', $_POST['signal-message-type']);
        update_metadata( 'post', $post->ID, 'signal_url', true);
        update_metadata( 'post', $post->ID, 'signal_extra_message', $_POST['signal-extra-message']);
    }else{
        delete_metadata( 'post', $post->ID, 'send_signal');
        delete_metadata( 'post', $post->ID, 'signal_message_type');
        delete_metadata( 'post', $post->ID, 'signa_-url');
        delete_metadata( 'post', $post->ID, 'signal_extra_message');
    }
}

add_action( 'wp_after_insert_post', __NAMESPACE__.'\afterInsertPost', 10, 3);
function afterInsertPost( $postId, $post ){
    if(in_array($post->post_status, ['publish'])){        
        //Send signal message
        sendPostNotification($post);
    }
}