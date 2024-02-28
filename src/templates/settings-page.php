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

            <tr>
                <th scope="row">
                    <label for="secret">
                        <?php _e('ICAR API secret') ?>
                    </label>
                </th>
                <td>
                    <input 
                        name="secret" 
                        type="text" 
                        id="secret" 
                        value="<?php echo $settings['secret'] ?? '' ?>" 
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

    <p class="import">
        <a href="<?php echo admin_url('admin-ajax.php?action=force_products_import') ?>" class="link" id="force">
            <?php _e('Force products import') ?>
        </a>
        <span>
            <?php _e('Next import: ') ?>
        </span>
        <span>
            <?php echo date( 'Y/d/m H:i:s', wp_next_scheduled('import_products') ) ?>
        </span>
    </p>

    <p class="logs">
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
        data.append('settings[secret]', document.querySelector('#secret').value)
        fetch('/wp-admin/admin-ajax.php?action=icar_api_update_settings', {
            method: 'POST',
            body: data,
        }).then(async res => {
            if (! res.ok) {
                throw new Error(res.statusText)
            }

            const data = await res.json()
            
            if (data.success) {
                alert('Successfully saved.')
            } else {
                throw new Error()
            }
        }).catch(err => alert(err))
    })

    const forceLink = document.querySelector('#force')
    forceLink.addEventListener('click', function (e) {
        e.preventDefault()

        if (! confirm('ARE YOU SURE YOU WANT TO FORCE PRODUCTS IMPORT?')) {
            return
        }

        fetch(this.getAttribute('href'))
            .then(res => alert('Import is running.'))
            .catch(err => alert(err))
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