<?php

namespace TSJIPPY\SIGNAL;

use TSJIPPY;

add_action('tsjippy-frontend-content-post-after-content', __NAMESPACE__ . '\afterContent', 20);
function afterContent($frontendContend)
{
    $checked        = '';
    $messageType    = '';
    $defaultGroups  = [];

    if (
        $frontendContend->fullrights &&                             // we have publish rights
        (
            $frontendContend->postId == null ||                     // this is a new page
            !empty($frontendContend->getPostMeta('send_signal', false))    // we should send a signal message
        )
    ) {
        $checked       = 'checked';
        $messageType   = $frontendContend->getPostMeta('signal_message_type', '');
    }

    $signalGroups      = [];
    if (SETTINGS['local'] ?? false) {
        $signal        = getSignalInstance();
        $signalGroups  = $signal->listGroups();
        $defaultGroups = SETTINGS['groups'] ?? [];
    }

    ?>
    <tbody id="signal-message" class="frontend-form expand-wrapper">
        <tr>
            <td>
                <h4>
                    Signal
                </h4>
            </td>
            <td>
                <button class="button small expand" type='button'>
                    &#9660;
                </button>
            </td>
        </tr>

        <tr>
            <td class="hidden expandable" collspan=2>
                <label>
                    <input type='checkbox' name='send-signal' value='1' <?php echo esc_attr($checked); ?>>
                    Send signal message on <?php echo $frontendContend->update ? 'update' : 'publish'; ?>
                </label>

                <div class='signal-message-type' style='margin-top:15px;'>
                    <?php
                    if (!empty($signalGroups) && is_array($signalGroups)) {
                    ?>
                        <label>
                            Target Signal Groups<br>
                            <?php
                            if (count($signalGroups) < 6) {
                                foreach ($signalGroups as $group) {
                                    if (empty($group->name)) {
                                        continue;
                                    }
                            ?>
                                    <label>
                                        <input type='checkbox' name='signal-groups[]' value='<?php echo esc_attr($group->id); ?>' <?php if (count($signalGroups) == 1 || in_array($group->id, $defaultGroups)) echo  'checked'; ?>>
                                        <?php echo esc_attr($group->name); ?>
                                    </label>
                                <?php
                                }
                            } else {
                                ?>
                                <select name="signal-groups[]" multiple="multiple" class="hidden-select">
                                    <?php
                                    foreach ($signalGroups as $group) {
                                        if (empty($group->name)) {
                                            continue;
                                        }
                                    ?>
                                        <option value='<?php echo esc_attr($group->id); ?>' <?php if (in_array($group->id, $defaultGroups)) echo 'selected'; ?>>
                                            <?php echo esc_attr($group->name); ?>
                                        </option>
                                    <?php
                                    }
                                    ?>
                                </select>

                            <?php
                            }
                            ?>
                        </label>
                        <br>
                    <?php
                    }
                    ?>
                    <br>
                    <label>
                        <input
                            type='radio'
                            name='signal-message-type'
                            value='summary'
                            <?php if ($messageType != 'all') echo 'checked'; ?>>
                        Send a summary
                    </label>
                    <label>
                        <input
                            type='radio'
                            name='signal-message-type'
                            value='all'
                            <?php if ($messageType == 'all') echo 'checked'; ?>>
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
            </td>
        </tr>
    </tbody>
<?php
}

// Send Signal message about the new or updated post
/**
 * Allow comments
 * 
 * @param   \WP_Post    $post       The new or updated post
 * @param   object      $object     FrontEndContent Instance
 * @param   array       $request    The sanitized request data
 */
add_action('tsjippy-frontend-content-after-post-save', __NAMESPACE__ . '\afterPostSave', 999, 3);
function afterPostSave($post, $object, $request)
{
    if (isset($request['send-signal']) && $request['send-signal']) {
        update_metadata('post', $post->ID, 'send_signal', true);
        update_metadata('post', $post->ID, 'signal_groups', $request['signal-groups']);
        update_metadata('post', $post->ID, 'signal_message_type', $request['signal-message-type']);
        update_metadata('post', $post->ID, 'signal_url', true);
        update_metadata('post', $post->ID, 'signal_extra_message', $request['signal-extra-message']);
    } else {
        delete_metadata('post', $post->ID, 'send_signal');
        delete_metadata('post', $post->ID, 'signal_groups');
        delete_metadata('post', $post->ID, 'signal_message_type');
        delete_metadata('post', $post->ID, 'signa_-url');
        delete_metadata('post', $post->ID, 'signal_extra_message');
    }
}

add_action('wp_after_insert_post', __NAMESPACE__ . '\afterInsertPost', 10, 3);
function afterInsertPost($postId, $post)
{
    if (in_array($post->post_status, ['publish'])) {
        //Send signal message
        sendPostNotification($post);
    }
}
