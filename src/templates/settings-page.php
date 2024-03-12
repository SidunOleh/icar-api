<div class="wrap">

    <div id="settings">

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

        <input 
            type="submit" 
            name="save-settings" 
            id="settings-btn" 
            class="button button-primary" 
            value="<?php _e('Save settings') ?>">
    </div>

    <hr>

    <div id="import">
        <input 
            type="file" 
            name="xlsx" 
            id="xlsx"
            accept=".xlsx">
        <br><br>
        <input 
            type="submit"
            name="import-products"
            class="button button-primary" 
            id="import-btn"
            value="<?php _e('Import products') ?>">
    </div>

    <hr>

    <div id="force">
        <span>
            <?php echo date( '\N\e\x\t\ \u\p\d\a\t\e\: Y/d/m H:i:s', wp_next_scheduled('products_update') ) ?>
        </span>
        <br><br>
        <input 
            type="submit"
            name="force-products-update"
            class="button button-primary" 
            id="force-btn"
            value="<?php _e('Force products update') ?>">
    </div>

    <hr>

    <div id="logs">
        <a href="/wp-content/plugins/icar-api/logs" class="link" target="_blank">
            <?php _e('Logs') ?>
        </a>
    </div>

</div>

<script>
    const settingsSection = document.querySelector('#settings')
    const settingsBtn = document.querySelector('#settings-btn')
    settingsBtn.addEventListener('click', function () {
        settingsSection.classList.add('loading')

        const data = new FormData()
        data.append('action', 'icar_api_update_settings')
        data.append('settings[login]', document.querySelector('#login').value)
        data.append('settings[password]', document.querySelector('#password').value)
        data.append('settings[secret]', document.querySelector('#secret').value)
        
        fetch('/wp-admin/admin-ajax.php', {
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
            .finally(() => settingsSection.classList.remove('loading'))
    })

    const importSection = document.querySelector('#import')
    const importBnt = document.querySelector('#import-btn')
    importBnt.addEventListener('click', function (e) {
        importSection.classList.add('loading')

        const data = new FormData()
        data.append('action', 'import_products')
        data.append('xlsx', document.querySelector('#xlsx').files[0])

        fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: data,
        }).then(async res => {
            if (! res.ok) {
                throw new Error(res.statusText)
            }

            const data = await res.json()
            
            if (data.success) {
                alert('Successfully imported.')
            } else {
                throw new Error(data.data.msg)
            }
        }).catch(err => alert(err))
            .finally(() => importSection.classList.remove('loading'))
    })

    const forceSection = document.querySelector('#force')
    const forceBtn = document.querySelector('#force-btn')
    forceBtn.addEventListener('click', function (e) {
        if (! confirm('ARE YOU SURE YOU WANT TO FORCE PRODUCTS UPDATE?')) {
            return
        }

        forceSection.classList.add('loading')

        const data = new FormData()
        data.append('action', 'force_products_update')

        fetch('/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: data,
        }).then(async res => {
            if (! res.ok) {
                throw new Error(res.statusText)
            }

            const data = await res.json()
            
            if (data.success) {
                alert('Successfully updated.')
            } else {
                throw new Error(data.data.msg)
            }
        }).catch(err => alert(err))
            .finally(() => forceSection.classList.remove('loading'))
    })
</script>

<style>
    .wrap > div {
        padding: 25px 0;
    }

    .wrap > div:first-child {
        padding-top: 0px;
    }

    .loading {
        position: relative;
    }

    .loading::before {
        content: "";
        position: absolute;
        z-index: 10;
        top: 0;
        left: 0;
        background: -webkit-gradient(linear, left top, right bottom, color-stop(40%, #eeeeee), color-stop(50%, #dddddd), color-stop(60%, #eeeeee));
        background: linear-gradient(to bottom right, #eeeeee 40%, #dddddd 50%, #eeeeee 60%);
        background-size: 200% 200%;
        background-repeat: no-repeat;
        -webkit-animation: placeholderShimmer 2s infinite linear;
                animation: placeholderShimmer 2s infinite linear;
        height: 108%;
        width: 100%;
        opacity: 0.6;
    }

    @-webkit-keyframes placeholderShimmer {
        0% {
            background-position: 100% 100%;
        }
        100% {
            background-position: 0 0;
        }
    }

    @keyframes placeholderShimmer {
        0% {
            background-position: 100% 100%;
        }
        100% {
            background-position: 0 0;
        }
    }
</style>