<?php
$title = (string) ($this->_['title'] ?? 'DataHawk Materialization');
$message = (string) ($this->_['message'] ?? 'This administration view is not implemented yet.');
?>
<style>
        .datahawk-materialization-placeholder {
                max-width: 1100px;
        }

        .datahawk-materialization-placeholder h1 {
                margin: 0 0 8px 0;
                font-size: 24px;
                line-height: 1.2;
                font-weight: 600;
        }

        .datahawk-materialization-placeholder p {
                margin: 0 0 12px 0;
                max-width: 900px;
                color: #555;
                line-height: 1.45;
        }

        .datahawk-materialization-placeholder-box {
                margin-top: 12px;
                padding: 12px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                background: #fff;
                color: #555;
        }
</style>
<div class="datahawk-materialization-placeholder">
        <h1><?php echo htmlspecialchars($title, ENT_QUOTES); ?></h1>
        <div class="datahawk-materialization-placeholder-box">
                <p><?php echo htmlspecialchars($message, ENT_QUOTES); ?></p>
        </div>
</div>
