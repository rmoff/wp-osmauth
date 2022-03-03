<?php /* Template Name: No title template */ ?>

<?php
get_header();
b4st_main_before();
?>

<?php //get_template_part('loops/banner'); 
?>

<main id="main" style="height:100%;" class="d-flex justify-content-center" class="container">
    <div class="column text-center">
        <section class="text-start">
            <?php the_content() ?>
            <?php wp_link_pages(); ?>
        </section>
        <?php
        if (get_query_var('err')) { ?>
            <div class="alert alert-warning" role="alert">
                <?php
                if (get_query_var('err') == "noSections") { ?>
                    No valid sections found on your account for this group.
                <?php }
                ?>
            </div>
        <?php }
        ?>
        <div class="">
            <a type="button" role="button" href="<?php osm_login_url() ?>" class="text-decoration-none btn btn-scout-purple active">
                <img style="height:1em;margin:0 0.5em;vertical-align:middle" src="https://www.onlinescoutmanager.co.uk/content/images/site-logo-wo.svg" />Scouters
            </a>
            <a type="button" href="<?php ogm_login_url() ?>" class="text-decoration-none btn btn-guide-teal">
                <img style="height:1em;margin:0 0.5em;vertical-align:middle" src="https://www.onlineguidemanager.co.uk/content/_organisations/guides/images/site-logo-wo.svg" />Guiders
            </a>
            <a class="text-decoration-none btn btn-scout-green" type="button" data-toggle="collapse" data-target="#collapseExample" aria-expanded="false" aria-controls="collapseExample">
                Admin Login
            </a>
        </div>
        <div class="text-start">
            <div class="collapse" id="collapseExample">
                <div class="card card-body">
                    <div class="alert alert-danger" role="alert">
                        This is for admins only. Parents and Leaders should login through the OSM or OGM buttons above.
                    </div>
                    <?php wp_login_form() ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
b4st_main_after();
get_footer();
?>