<div class="wrap">

    <?php $settings = get_option('icar_api_settings'); ?>

    <table class="form-table" role="presentation">
        <tbody>

            <tr>
                <th scope="row">
                    <label for="login">
                        <?php _e('ICAR API login') ?>
                    </label>
                </th>
                <td>
                    <input 
                        name="login" 
                        type="text" 
                        id="login" 
                        value="<?php echo $settings['login'] ?? '' ?>" 
                        class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="password">
                        <?php _e('ICAR API password') ?>
                    </label>
                </th>
                <td>
                    <input 
                        name="password" 
                        type="text" 
                        id="password" 
                        value="<?php echo $settings['password'] ?? '' ?>" 
                        class="regular-text">
                </td>
            </tr>

        </tbody>
    </table>

    <p class="submit">
        <input 
            type="submit" 
            name="submit" 
            id="submit" 
            class="button button-primary" 
            value="<?php _e('Save Changes') ?>">
    </p>

    <p class="links">
        <a class="link" id="force">
            <?php _e('Force products import') ?>
        </a>

        <a href="/wp-content/plugins/icar-api/logs/imports?C=M;O=D" class="link" target="_blank">
            <?php _e('Logs') ?>
        </a>
    </p>

</div>

<script>
    const btn = document.querySelector('#submit')
    btn.addEventListener('click', function () {
        const data = new FormData()
        data.append('settings[login]', document.querySelector('#login').value)
        data.append('settings[password]', document.querySelector('#password').value)
        fetch('/wp-admin/admin-ajax.php?action=icar_api_update_settings', {
            method: 'POST',
            body: data,
        }).then(async res => {
            if (res.status != 200) {
                throw new Error()
            }

            const data = await res.json()
            
            if (data.success) {
                alert('Successfully saved.')
            } else {
                alert('Error. Try again.')
            }
        }).catch(err => {
            alert(err ?? 'Error. Try again.')
        })
    })

    const forceLink = document.querySelector('#force')
    forceLink.addEventListener('click', function () {
        if (! confirm('Are you sure want to force products import?')) {
            return
        }

        fetch('/wp-admin/admin-ajax.php?action=force_products_import')
            .then(async res => {
                if (res.status != 200) {
                    throw new Error()
                }

                const data = await res.json()

                if (data.success) {
                    alert('Import is running.')
                } else {
                    alert('Can\'t run import. Try later.')
                }
            }).catch(err => {
                alert(err ?? 'Error. Try again.')
            })
    })
</script>

<style>
    .link {
        font-size: 17px;
        margin-right: 15px;
        text-decoration: none;
        cursor: pointer;
    }
</style>